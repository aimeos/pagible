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
                    fn( string $t ) => str_starts_with( $t, 'cms_' ) && $t !== 'cms_index'
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
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
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
            $this->table( ['Table', 'Records'], collect( $counts )->map( fn( $c, $t ) => [$t, $c] )->values()->toArray() );

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
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
        );

        foreach( $iterator as $file )
        {
            if( !$file->isFile() ) {
                continue;
            }

            $hash = hash_file( 'sha256', $file->getPathname() );

            if( $hash === false ) {
                throw new \RuntimeException( 'Failed to checksum backup entry: ' . $file->getFilename() );
            }

            $path = substr( $file->getPathname(), strlen( $dir ) + 1 );
            $checksums[str_replace( DIRECTORY_SEPARATOR, '/', $path )] = $hash;
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
        $storages = [];
        $count = 0;

        foreach( $this->media( $tenant ) as [$logical, $file] )
        {
            $storage = $storages[$logical] ??= Storage::disk( File::diskName( $logical ) );
            $size = $storage->size( $file );
            $mediaDir = $dir . '/media/' . $logical;
            $target = $mediaDir . '/' . $file;
            $targetDir = dirname( $target );

            if( !is_dir( $targetDir ) ) {
                if( !mkdir( $targetDir, 0755, true ) && !is_dir( $targetDir ) ) {
                    throw new \RuntimeException( 'Failed to create media backup directory' );
                }
            }

            $stream = $storage->readStream( $file );

            if( !$stream ) {
                throw new \RuntimeException( 'Failed to read media file: ' . $file );
            }

            try
            {
                $out = fopen( $target, 'w' );

                if( !$out ) {
                    throw new \RuntimeException( 'Failed to create media backup file' );
                }

                try
                {
                    $written = stream_copy_to_stream( $stream, $out );

                    if( $written === false || $written !== $size ) {
                        throw new \RuntimeException( 'Failed to copy media file: ' . $file );
                    }
                }
                finally
                {
                    fclose( $out );
                }
            }
            finally
            {
                if( is_resource( $stream ) ) {
                    fclose( $stream );
                }
            }

            if( filesize( $target ) !== $size ) {
                throw new \RuntimeException( 'Failed to verify media file: ' . $file );
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
        $opened = false;

        try
        {
            if( $zip->open( $zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
                throw new \RuntimeException( 'Failed to create ZIP archive' );
            }

            $opened = true;
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach( $iterator as $file )
            {
                $relativePath = substr( $file->getPathname(), strlen( $dir ) + 1 );
                $isMedia = str_starts_with( $relativePath, 'media/' );

                if( !$zip->addFile( $file->getPathname(), $relativePath ) ) {
                    throw new \RuntimeException( 'Failed to add ZIP entry: ' . $relativePath );
                }

                if( $isMedia && !$zip->setCompressionName( $relativePath, \ZipArchive::CM_STORE ) ) {
                    throw new \RuntimeException( 'Failed to configure ZIP entry: ' . $relativePath );
                }
            }

            if( !$zip->close() ) {
                throw new \RuntimeException( 'Failed to finish ZIP archive' );
            }

            $opened = false;
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
            if( $opened ) {
                $zip->close();
            }

            @unlink( $zipPath );
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
            fwrite( $fh, json_encode( (array) $row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
            $count++;
        }

        fclose( $fh );

        return $count;
    }


    /**
     * Decodes a JSON object into an associative array.
     *
     * @return array<string, mixed>
     */
    protected function json( mixed $value ): array
    {
        if( is_string( $value ) ) {
            $value = json_decode( $value, true );
        } elseif( is_object( $value ) ) {
            $value = (array) $value;
        }

        return is_array( $value ) ? $value : [];
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
                $paths = [
                    $file->path,
                    ...array_values( $this->json( $file->previews ) ),
                ];

                foreach( $versions->get( $file->id, [] ) as $version )
                {
                    $data = $this->json( $version->data );
                    $paths[] = $data['path'] ?? null;
                    array_push( $paths, ...array_values( (array) ( $data['previews'] ?? [] ) ) );
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
        $prefix = 'pagible-' . $tenant . '-';

        $files = collect( $storage->files() )
            ->filter( fn( string $f ) => str_starts_with( basename( $f ), $prefix ) && str_ends_with( $f, '.zip' ) )
            ->sort()
            ->values();

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
        if( is_link( $dir ) ) {
            @unlink( $dir );
            return;
        }

        if( !is_dir( $dir ) ) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach( $iterator as $item )
        {
            if( $item->isDir() ) {
                rmdir( $item->getPathname() );
            } else {
                unlink( $item->getPathname() );
            }
        }

        rmdir( $dir );
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
