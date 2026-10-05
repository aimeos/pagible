<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Events\BackupCreated;
use Aimeos\Cms\Models\File;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class Backup extends Command
{
    use BackupTrait;

    /**
     * Tenant ownership for CMS relationship tables without their own tenant_id.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const OWNERS = [
        'cms_element_file' => ['element_id', 'cms_elements'],
        'cms_page_element' => ['page_id', 'cms_pages'],
        'cms_page_file' => ['page_id', 'cms_pages'],
        'cms_version_element' => ['version_id', 'cms_versions'],
        'cms_version_file' => ['version_id', 'cms_versions'],
    ];


    protected $signature = 'cms:backup
        {--tenant= : Tenant ID to backup}
        {--disk= : Storage disk for the backup}
        {--keep= : Number of backups to keep (deletes oldest)}
        {--no-media : Skip media files}';

    protected $description = 'Create a backup of CMS data';


    public function handle(): int
    {
        $optTenant = $this->option( 'tenant' );
        $tenant = is_string( $optTenant ) ? $optTenant : Tenancy::value();
        $optDisk = $this->option( 'disk' );
        $disk = is_string( $optDisk ) ? $optDisk : 'local';
        $noMedia = $this->option( 'no-media' );

        try {
            $tenant = Tenancy::check( $tenant );
        } catch( \Throwable $e ) {
            $this->error( 'Backup failed: ' . $e->getMessage() );
            return Command::FAILURE;
        }

        $tmpDir = null;

        try
        {
            $tmpDir = $this->tmpDir();

            [$zipPath, $counts] = Utils::storageLock( $tenant, function() use ( $disk, $noMedia, $tenant, $tmpDir ) {
                $db = DB::connection( config( 'cms.db', 'sqlite' ) );
                $allTables = $db->getSchemaBuilder()->getTables();
                $cmsTables = array_filter(
                    array_column( $allTables, 'name' ),
                    fn( string $t ) => str_starts_with( $t, 'cms_' ) && !str_starts_with( $t, 'cms_index' )
                );
                $columns = $this->classify( $db, $cmsTables );
                $counts = [];

                $this->info( 'Exporting database tables...' );

                foreach( $columns as $table => $cols )
                {
                    $query = $this->query( $db, $table, $cols, $tenant );

                    if( in_array( '_lft', $cols ) ) {
                        $query->orderBy( '_lft' );
                    } elseif( in_array( 'id', $cols ) ) {
                        $query->orderBy( 'id' );
                    }

                    $counts[$table] = $this->export( $query->cursor(), $tmpDir . '/' . $table . '.ndjson' );

                    $this->line( sprintf( '  %s: %d records', $table, $counts[$table] ), null, 'v' );
                }

                if( !$noMedia )
                {
                    $this->info( 'Copying media files...' );
                    $mediaCount = $this->copyMedia( $tenant, $tmpDir );
                    $this->line( sprintf( '  %d media files', $mediaCount ), null, 'v' );
                }

                $manifest = [
                    'format_version' => '3',
                    'tenant_id' => $tenant,
                    'counts' => $counts,
                    'checksums' => $this->checksums( $tmpDir ),
                    'timestamp' => now()->toIso8601String(),
                ];
                $manifest['signature'] = $this->sign( $manifest );

                $written = file_put_contents( $tmpDir . '/manifest.json', json_encode(
                    $manifest,
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ) . "\n" );

                if( $written === false ) {
                    throw new \RuntimeException( 'Failed to write backup manifest' );
                }

                $this->info( 'Creating ZIP archive...' );
                $zipFile = sprintf( 'pagible-%s-%s.zip', $tenant, now()->format( 'Y-m-d\THis.v' ) );
                $zipPath = $this->createZip( $tmpDir, $disk, $zipFile );

                if( $this->option( 'keep' ) ) {
                    $this->prune( $disk, $tenant, (int) $this->option( 'keep' ) );
                }

                return [$zipPath, $counts];
            }, 0 );

            BackupCreated::dispatch( $tenant, $zipPath, $counts );

            $this->info( sprintf( 'Backup created: %s', $zipPath ) );
            $this->table( ['Table', 'Records'], array_map( null, array_keys( $counts ), $counts ) );

            return Command::SUCCESS;
        }
        catch( LockTimeoutException )
        {
            $this->warn( 'Another backup/restore or media operation is in progress for this tenant.' );
            return Command::FAILURE;
        }
        catch( \Throwable $e )
        {
            $this->error( 'Backup failed: ' . $e->getMessage() );
            return Command::FAILURE;
        }
        finally
        {
            if( $tmpDir !== null ) {
                $this->removeDir( $tmpDir );
            }
        }
    }


    /**
     * Computes SHA-256 checksums for all database and media files.
     *
     * @param string $dir Temp directory path
     * @return array<string, string> Filename => checksum map
     */
    protected function checksums( string $dir ): array
    {
        $checksums = [];

        foreach( $this->entries( $dir ) as $name => $path )
        {
            if( ( $hash = hash_file( 'sha256', $path ) ) === false ) {
                throw new \RuntimeException( 'Failed to checksum backup entry: ' . basename( $path ) );
            }

            $checksums[$name] = $hash;
        }

        ksort( $checksums );
        return $checksums;
    }


    /**
     * Copies media files for the tenant into the temp directory.
     *
     * @param string $tenant Tenant ID
     * @param string $dir Temp directory path
     * @return int Number of files copied
     */
    protected function copyMedia( string $tenant, string $dir ): int
    {
        $filesystem = new Filesystem();
        $storages = [];
        $count = 0;

        foreach( $this->media( $tenant ) as [$logical, $file] )
        {
            $storage = $storages[$logical] ??= Storage::disk( File::diskName( $logical ) );
            $size = $storage->size( $file );
            $target = $dir . '/media/' . $logical . '/' . $file;

            $filesystem->ensureDirectoryExists( dirname( $target ) );

            if( !( $stream = $storage->readStream( $file ) ) ) {
                throw new \RuntimeException( 'Failed to read media file: ' . $file );
            }

            try {
                $written = file_put_contents( $target, $stream );
            } finally {
                fclose( $stream );
            }

            if( $written !== $size || filesize( $target ) !== $size ) {
                throw new \RuntimeException( 'Failed to copy media file: ' . $file );
            }

            $count++;
        }

        return $count;
    }


    /**
     * Creates a ZIP archive from the temp directory and streams it to the target disk.
     *
     * @param string $dir Temp directory path
     * @param string $disk Target storage disk name
     * @param string $filename ZIP filename
     * @return string Path of the created ZIP on the disk
     */
    protected function createZip( string $dir, string $disk, string $filename ): string
    {
        $zipPath = $dir . '.zip';
        $zip = new \ZipArchive();

        try
        {
            if( $zip->open( $zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
                throw new \RuntimeException( 'Failed to create ZIP archive' );
            }

            try
            {
                foreach( $this->entries( $dir ) as $name => $path )
                {
                    if( !$zip->addFile( $path, $name ) ) {
                        throw new \RuntimeException( 'Failed to add ZIP entry: ' . $name );
                    }

                    if( str_starts_with( $name, 'media/' ) && !$zip->setCompressionName( $name, \ZipArchive::CM_STORE ) ) {
                        throw new \RuntimeException( 'Failed to configure ZIP entry: ' . $name );
                    }
                }
            }
            finally
            {
                $closed = $zip->close();
            }

            if( !$closed ) {
                throw new \RuntimeException( 'Failed to finish ZIP archive' );
            }

            $size = filesize( $zipPath );

            if( $size === false ) {
                throw new \RuntimeException( 'Failed to determine ZIP file size' );
            }

            $stream = fopen( $zipPath, 'r' );

            if( !$stream ) {
                throw new \RuntimeException( 'Failed to open ZIP file for streaming' );
            }

            $storage = Storage::disk( $disk );
            $verified = false;

            try
            {
                if( !$storage->writeStream( $filename, $stream ) ) {
                    throw new \RuntimeException( 'Failed to store ZIP archive' );
                }

                if( !$storage->exists( $filename ) || $storage->size( $filename ) !== $size ) {
                    throw new \RuntimeException( 'Failed to verify ZIP archive' );
                }

                $verified = true;
                return $filename;
            }
            finally
            {
                if( is_resource( $stream ) ) {
                    fclose( $stream );
                }

                if( !$verified )
                {
                    try {
                        $storage->delete( $filename );
                    } catch( \Throwable $e ) {
                        report( $e );
                    }
                }
            }
        }
        finally
        {
            @unlink( $zipPath );
        }
    }


    /**
     * Yields the files below the directory as relative entry name => file path.
     *
     * @param string $dir Directory path
     * @return \Generator<string, string> Entry name => file path
     */
    protected function entries( string $dir ): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
        );

        foreach( $iterator as $file )
        {
            if( $file->isFile() ) {
                yield str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) ) => $file->getPathname();
            }
        }
    }


    /**
     * Exports a database cursor to an NDJSON file.
     *
     * @param iterable<object> $cursor Database cursor
     * @param string $file Target file path
     * @return int Number of records exported
     */
    protected function export( iterable $cursor, string $file ): int
    {
        $count = 0;
        $fh = fopen( $file, 'w' );

        if( !$fh ) {
            throw new \RuntimeException( 'Failed to create NDJSON file: ' . basename( $file ) );
        }

        foreach( $cursor as $row )
        {
            $line = json_encode( (array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

            if( fwrite( $fh, $line . "\n" ) === false ) {
                throw new \RuntimeException( 'Failed to write NDJSON file: ' . basename( $file ) );
            }

            $count++;
        }

        if( !fclose( $fh ) ) {
            throw new \RuntimeException( 'Failed to close NDJSON file: ' . basename( $file ) );
        }

        return $count;
    }


    /**
     * Yields unique catalog-owned media paths with their logical disk.
     *
     * @return \Generator<int, array{0: string, 1: string}>
     */
    protected function media( string $tenant ): \Generator
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $last = null;

        do
        {
            $query = $db->table( 'cms_files' )
                ->select( 'id', 'disk', 'path', 'previews' )
                ->where( 'tenant_id', $tenant )->orderBy( 'id' )->limit( 250 );

            if( $last !== null ) {
                $query->where( 'id', '>', $last );
            }

            $files = $query->get();

            if( $files->isEmpty() ) {
                return;
            }

            $versions = $db->table( 'cms_versions' )
                ->select( 'versionable_id', 'data' )
                ->where( 'tenant_id', $tenant )
                ->where( 'versionable_type', File::class )
                ->whereIn( 'versionable_id', $files->pluck( 'id' ) )
                ->get()->groupBy( 'versionable_id' );

            foreach( $files as $file )
            {
                $paths = self::filePaths( (array) $file );

                foreach( $versions->get( $file->id, [] ) as $version ) {
                    array_push( $paths, ...self::versionPaths( (array) $version ) );
                }

                $seen = [];

                foreach( $paths as $path )
                {
                    $path = Utils::normalizePath( $path, $tenant );

                    if( $path === null || !File::owns( $tenant, (string) $file->id, $path )
                        || isset( $seen[$path] ) ) {
                        continue;
                    }

                    $seen[$path] = true;
                    yield [(string) $file->disk, $path];
                }
            }

            $last = (string) $files->last()->id;
        }
        while( $files->count() === 250 );
    }


    /**
     * Deletes old backups, keeping the N most recent.
     *
     * @param string $disk Storage disk name
     * @param string $tenant Tenant ID
     * @param int $keep Number of backups to keep
     */
    protected function prune( string $disk, string $tenant, int $keep ): void
    {
        $storage = Storage::disk( $disk );
        $files = $this->backups( $storage, $tenant );

        $toDelete = $files->slice( 0, max( 0, $files->count() - $keep ) );

        foreach( $toDelete as $file )
        {
            $storage->delete( $file );
            $this->line( sprintf( '  Deleted old backup: %s', $file ), null, 'v' );
        }
    }


    /**
     * Returns a tenant-scoped export query.
     *
     * @param list<string> $columns
     */
    protected function query( Connection $db, string $table, array $columns, string $tenant ): Builder
    {
        $query = $db->table( $table );

        if( in_array( 'tenant_id', $columns, true ) ) {
            return $query->where( 'tenant_id', $tenant );
        }

        $relation = self::OWNERS[$table] ?? null;

        if( $relation === null ) {
            throw new \RuntimeException( sprintf( 'Tenant ownership is undefined for table "%s"', $table ) );
        }

        [$column, $owner] = $relation;

        return $query->whereIn(
            $column,
            $db->table( $owner )->select( 'id' )->where( 'tenant_id', $tenant ),
        );
    }


    /**
     * Recursively removes a directory and its contents.
     *
     * @param string $dir Directory path
     */
    protected function removeDir( string $dir ): void
    {
        // deleteDirectory() would follow a symlinked root directory
        if( is_link( $dir ) ) {
            @unlink( $dir );
        } else {
            ( new Filesystem() )->deleteDirectory( $dir );
        }
    }


    /**
     * Creates a temporary directory for building the backup.
     *
     * @return string Path to the temp directory
     */
    protected function tmpDir(): string
    {
        for( $i = 0; $i < 5; $i++ )
        {
            $path = $this->tempdir() . '/cms-backup-tmp-' . bin2hex( random_bytes( 16 ) );

            if( @mkdir( $path, 0700 ) ) {
                return $path;
            }
        }

        throw new \RuntimeException( 'Failed to create temporary backup directory' );
    }
}
