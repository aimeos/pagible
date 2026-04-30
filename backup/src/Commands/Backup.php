<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Events\BackupCreated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class Backup extends Command
{
    use BackupTrait;


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

        $lock = Cache::lock( 'cms_backup_' . $tenant, 300 );

        if( !$lock->get() )
        {
            $this->warn( 'Another backup/restore operation is in progress for this tenant.' );
            return Command::FAILURE;
        }

        $tmpDir = $this->tmpDir();

        try
        {
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
                $query = $db->table( $table );

                if( in_array( 'tenant_id', $cols ) ) {
                    $query->where( 'tenant_id', $tenant );
                }

                if( in_array( '_lft', $cols ) ) {
                    $query->orderBy( '_lft' );
                } elseif( in_array( 'id', $cols ) ) {
                    $query->orderBy( 'id' );
                }

                $counts[$table] = $this->export( $query->cursor(), $tmpDir . '/' . $table . '.ndjson' );

                $this->line( sprintf( '  %s: %d records', $table, $counts[$table] ), null, 'v' );
            }

            $checksums = $this->checksums( $tmpDir );

            $manifest = [
                'format_version' => '1',
                'tenant_id' => $tenant,
                'counts' => $counts,
                'checksums' => $checksums,
                'timestamp' => now()->toIso8601String(),
            ];

            file_put_contents( $tmpDir . '/manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );

            if( !$noMedia )
            {
                $this->info( 'Copying media files...' );
                $mediaCount = $this->copyMedia( $tenant, $tmpDir );
                $this->line( sprintf( '  %d media files', $mediaCount ), null, 'v' );
            }

            $this->info( 'Creating ZIP archive...' );
            $zipFile = sprintf( 'pagible-%s-%s.zip', $tenant, now()->format( 'Y-m-d\THis.v' ) );
            $zipPath = $this->createZip( $tmpDir, $disk, $zipFile );

            if( $this->option( 'keep' ) ) {
                $this->prune( $disk, $tenant, (int) $this->option( 'keep' ) );
            }

            BackupCreated::dispatch( $tenant, $zipPath, $counts );

            $this->info( sprintf( 'Backup created: %s', $zipPath ) );
            $this->table( ['Table', 'Records'], collect( $counts )->map( fn( $c, $t ) => [$t, $c] )->values()->toArray() );

            return Command::SUCCESS;
        }
        catch( \Throwable $e )
        {
            $this->error( 'Backup failed: ' . $e->getMessage() );
            return Command::FAILURE;
        }
        finally
        {
            $this->removeDir( $tmpDir );
            $lock->forceRelease();
        }
    }


    /**
     * Computes SHA-256 checksums for all NDJSON files in the temp directory.
     *
     * @param string $dir Temp directory path
     * @return array<string, string> Filename => checksum map
     */
    protected function checksums( string $dir ): array
    {
        $checksums = [];

        foreach( glob( $dir . '/*.ndjson' ) ?: [] as $file )
        {
            $hash = hash_file( 'sha256', $file );

            if( $hash !== false ) {
                $checksums[basename( $file )] = $hash;
            }
        }

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
        $storage = Storage::disk( config( 'cms.disk', 'public' ) );
        $mediaDir = $dir . '/media';
        $prefix = 'cms/' . $tenant;
        $count = 0;

        if( !$storage->exists( $prefix ) ) {
            return 0;
        }

        $stack = [$prefix];

        while( $directory = array_pop( $stack ) )
        {
            foreach( $storage->directories( $directory ) as $subdir ) {
                $stack[] = $subdir;
            }

            foreach( $storage->files( $directory ) as $file )
            {
                $target = $mediaDir . '/' . $file;
                $targetDir = dirname( $target );

                if( !is_dir( $targetDir ) ) {
                    mkdir( $targetDir, 0755, true );
                }

                $stream = $storage->readStream( $file );

                if( $stream )
                {
                    $out = fopen( $target, 'w' );

                    if( $out ) {
                        stream_copy_to_stream( $stream, $out );
                        fclose( $out );
                    }

                    if( is_resource( $stream ) ) {
                        fclose( $stream );
                    }

                    $count++;
                }
            }
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

        if( $zip->open( $zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) !== true ) {
            throw new \RuntimeException( 'Failed to create ZIP archive' );
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach( $iterator as $file )
        {
            $relativePath = substr( $file->getPathname(), strlen( $dir ) + 1 );
            $isMedia = str_starts_with( $relativePath, 'media/' );

            $zip->addFile( $file->getPathname(), $relativePath );

            if( $isMedia ) {
                $zip->setCompressionName( $relativePath, \ZipArchive::CM_STORE );
            }
        }

        $zip->close();

        $stream = fopen( $zipPath, 'r' );

        if( !$stream ) {
            throw new \RuntimeException( 'Failed to open ZIP file for streaming' );
        }

        Storage::disk( $disk )->writeStream( $filename, $stream );

        if( is_resource( $stream ) ) {
            fclose( $stream );
        }

        @unlink( $zipPath );

        return $filename;
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
     * Recursively removes a directory and its contents.
     *
     * @param string $dir Directory path
     */
    protected function removeDir( string $dir ): void
    {
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
        $path = $this->tempdir() . '/cms-backup-tmp-' . uniqid();
        mkdir( $path, 0755, true );
        return $path;
    }
}
