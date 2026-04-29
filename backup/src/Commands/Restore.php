<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Events\RestoreCompleted;
use Aimeos\Cms\Events\RestoreFailed;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class Restore extends Command
{
    use BackupTrait;


    protected $signature = 'cms:restore
        {file? : Backup ZIP filename}
        {--tenant= : Target tenant ID}
        {--disk= : Storage disk containing the backup}
        {--merge : Merge (upsert) instead of replacing existing data}
        {--no-media : Skip media files}
        {--media-only : Only restore media files}
        {--list : List available backups}
        {--verify : Verify backup integrity without restoring}
        {--force : Skip confirmation prompts}';

    protected $description = 'Restore CMS data from a backup';

    /** Maximum NDJSON line length (10 MB) */
    private const MAX_LINE_LENGTH = 10_485_760;

    /** Maximum total extracted size (10 GB) */
    private const MAX_EXTRACTED_SIZE = 10_737_418_240;


    public function handle(): int
    {
        $disk = $this->option( 'disk' ) ?: 'local';

        if( $this->option( 'list' ) ) {
            return $this->list( $disk );
        }

        $file = $this->argument( 'file' );

        if( !$file || !is_string( $file ) )
        {
            $this->error( 'Please specify a backup file. Use --list to see available backups.' );
            return Command::FAILURE;
        }

        $tenant = $this->option( 'tenant' );
        $path = null;

        try
        {
            $path = $this->path( $disk, $file );
            $zip = $this->zip( $path );

            try
            {
                $manifest = $this->manifest( $zip );
                $tenant = $tenant ?? $manifest['tenant_id'] ?: Tenancy::value();

                if( $this->option( 'verify' ) ) {
                    return $this->verify( $zip, $manifest );
                }

                return $this->restore( $zip, $manifest, $tenant, $file );
            }
            finally
            {
                $zip->close();
            }
        }
        catch( \Throwable $e )
        {
            RestoreFailed::dispatch( $tenant ?: 'unknown', $e->getMessage() );
            $this->error( 'Restore failed: ' . $e->getMessage() );
            return Command::FAILURE;
        }
        finally
        {
            if( $path && str_starts_with( $path, $this->tempdir() ) ) {
                @unlink( $path );
            }
        }
    }


    /**
     * Deletes existing tenant data. Pivot rows are removed by CASCADE.
     *
     * @param Connection $db Database connection
     * @param string $tenant Tenant ID
     * @param array<string, list<string>> $columns Table name => column names
     */
    protected function cleanupDatabase( Connection $db, string $tenant, array $columns ): void
    {
        foreach( $columns as $table => $cols )
        {
            if( in_array( 'tenant_id', $cols ) ) {
                $db->table( $table )->where( 'tenant_id', $tenant )->delete();
            }
        }
    }


    /**
     * Cleans up media files tracked in the tracking file after a failed restore.
     *
     * @param string $trackingFile Path to the tracking file
     */
    protected function cleanupMedia( string $trackingFile ): void
    {
        if( !file_exists( $trackingFile ) ) {
            return;
        }

        $storage = Storage::disk( config( 'cms.disk', 'public' ) );
        $fh = fopen( $trackingFile, 'r' );

        if( !$fh ) {
            return;
        }

        while( ( $path = fgets( $fh ) ) !== false )
        {
            $path = trim( $path );

            if( $path && $storage->exists( $path ) ) {
                $storage->delete( $path );
            }
        }

        fclose( $fh );
    }


    /**
     * Discovers CMS table names present in the ZIP that also exist in the database.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param Connection $db Database connection
     * @return list<string> Table names
     */
    protected function discover( \ZipArchive $zip, Connection $db ): array
    {
        $list = [];
        $tables = array_flip( array_column( $db->getSchemaBuilder()->getTables(), 'name' ) );

        for( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $stat = $zip->statIndex( $i );

            if( $stat && str_starts_with( $stat['name'], 'cms_' ) && str_ends_with( $stat['name'], '.ndjson' ) )
            {
                $name = substr( $stat['name'], 0, -7 );

                if( isset( $tables[$name] ) ) {
                    $list[] = $name;
                }
            }
        }

        return $list;
    }


    /**
     * Runs post-restore tasks: rebuild tree, search index, flush cache, verify counts.
     *
     * @param string $tenant Tenant ID
     * @param string $file Backup filename
     * @param array<string, int> $counts Expected counts from manifest
     */
    protected function finalize( string $tenant, string $file, array $counts ): void
    {
        $this->info( 'Rebuilding page tree...' );
        Page::fixTree();

        $this->info( 'Rebuilding search index...' );
        Artisan::call( 'cms:index' );

        $this->info( 'Clearing page cache...' );
        Cache::flush();

        $this->verifyCounts( $tenant, $counts );

        RestoreCompleted::dispatch( $tenant, $file, $counts );

        $this->info( 'Restore completed successfully.' );
        $this->table( ['Table', 'Expected'], collect( $counts )->map( fn( $c, $t ) => [$t, $c] )->values()->toArray() );
    }


    /**
     * Formats a file size in bytes to a human-readable string.
     *
     * @param int $bytes File size in bytes
     * @return string Formatted size string
     */
    protected function format( int $bytes ): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $i = 0;

        while( $size >= 1024 && $i < count( $units ) - 1 )
        {
            $size /= 1024;
            ++$i;
        }

        return round( $size, 1 ) . ' ' . $units[$i];
    }


    /**
     * Imports an NDJSON file into a database table.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param Connection $db Database connection
     * @param string $table Database table name
     * @param list<string> $columns Allowed column names for this table
     * @param string $entry NDJSON entry name in ZIP
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param bool $merge Whether to use upsert (merge mode)
     * @return int Number of records imported
     */
    protected function import( \ZipArchive $zip, Connection $db, string $table, array $columns,
        string $entry, string $tenant, string $sourceTenant, bool $merge ): int
    {
        $stream = $zip->getStream( $entry );

        if( !$stream ) {
            return 0;
        }

        $count = 0;
        $buffer = [];
        $allowed = array_flip( $columns );
        $hasTenant = isset( $allowed['tenant_id'] );

        while( ( $line = fgets( $stream, self::MAX_LINE_LENGTH ) ) !== false )
        {
            if( !( $line = trim( (string) $line ) ) ) {
                continue;
            }

            $row = json_decode( $line, true );

            if( !is_array( $row ) ) {
                continue;
            }

            $row = $this->rewrite( array_intersect_key( $row, $allowed ), $table, $tenant, $sourceTenant, $hasTenant );
            $buffer[] = $row;
            $count++;

            if( count( $buffer ) >= 50 )
            {
                $this->insert( $db, $table, $buffer, $merge, $hasTenant );
                $buffer = [];
            }
        }

        if( $buffer ) {
            $this->insert( $db, $table, $buffer, $merge, $hasTenant );
        }

        if( is_resource( $stream ) ) {
            fclose( $stream );
        }

        return $count;
    }


    /**
     * Inserts or upserts a batch of records.
     *
     * @param Connection $db Database connection
     * @param string $table Table name
     * @param list<array<string, mixed>> $rows Batch of rows to insert
     * @param bool $merge Whether to use upsert
     * @param bool $hasTenant Whether the table has a tenant_id column
     */
    protected function insert( Connection $db, string $table, array $rows, bool $merge, bool $hasTenant ): void
    {
        $query = $db->table( $table );

        if( $merge && $hasTenant )
        {
            $columns = array_keys( $rows[0] ?? [] );
            $updateColumns = array_values( array_diff( $columns, ['id'] ) );
            $query->upsert( $rows, ['id'], $updateColumns );
        }
        elseif( $merge )
        {
            $query->insertOrIgnore( $rows );
        }
        else
        {
            $query->insert( $rows );
        }
    }


    /**
     * Lists available backup files on the disk.
     *
     * @param string $disk Storage disk name
     * @return int Command exit code
     */
    protected function list( string $disk ): int
    {
        $storage = Storage::disk( $disk );
        $tenant = $this->option( 'tenant' );
        $prefix = 'pagible-' . $tenant . '-';

        /** @var list<string> $allFiles */
        $allFiles = $storage->files();
        $files = collect( $allFiles )
            ->filter( fn( string $f ) => str_starts_with( basename( $f ), $prefix ) && str_ends_with( $f, '.zip' ) )
            ->sort()
            ->values();

        if( $files->isEmpty() )
        {
            $this->info( 'No backups found.' );
            return Command::SUCCESS;
        }

        $rows = $files->map( function( string $file ) use ( $storage ) {
            $size = $storage->size( $file );
            $date = date( 'Y-m-d H:i:s', $storage->lastModified( $file ) );
            return [basename( $file ), $this->format( $size ), $date];
        } )->toArray();

        $this->table( ['File', 'Size', 'Date'], $rows );
        return Command::SUCCESS;
    }


    /**
     * Reads and validates the manifest from the ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive
     * @return array<string, mixed> Manifest data
     */
    protected function manifest( \ZipArchive $zip ): array
    {
        $stream = $zip->getStream( 'manifest.json' );

        if( !$stream ) {
            throw new \RuntimeException( 'Backup is missing manifest.json' );
        }

        $json = stream_get_contents( $stream );
        fclose( $stream );

        if( $json === false ) {
            throw new \RuntimeException( 'Failed to read manifest.json' );
        }

        $manifest = json_decode( $json, true );

        if( !is_array( $manifest ) || !isset( $manifest['format_version'], $manifest['tenant_id'], $manifest['counts'] ) ) {
            throw new \RuntimeException( 'Invalid manifest format' );
        }

        return $manifest;
    }


    /**
     * Resolves the ZIP file to a local path, downloading to a temp file if needed.
     *
     * @param string $disk Storage disk name
     * @param string $file Backup filename
     * @return string Local file path to the ZIP
     */
    protected function path( string $disk, string $file ): string
    {
        $storage = Storage::disk( $disk );

        if( !$storage->exists( $file ) ) {
            throw new \RuntimeException( sprintf( 'Backup file not found: %s', $file ) );
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storage */
        $localPath = $storage->path( $file );

        if( file_exists( $localPath ) ) {
            return $localPath;
        }

        $tmpPath = $this->tempFilePath( 'cms-restore-' );
        $stream = $storage->readStream( $file );

        if( !$stream ) {
            throw new \RuntimeException( 'Failed to read backup file from disk' );
        }

        $out = fopen( $tmpPath, 'w' );

        if( !$out ) {
            throw new \RuntimeException( 'Failed to create temporary file for restore' );
        }

        stream_copy_to_stream( $stream, $out );
        fclose( $out );

        if( is_resource( $stream ) ) {
            fclose( $stream );
        }

        return $tmpPath;
    }


    /**
     * Resolves a media entry path to the target storage path, with tenant rewriting and validation.
     *
     * @param string $entryName ZIP entry name (e.g. "media/cms/tenant/file.jpg")
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @return string|null Target storage path, or null if the entry should be skipped
     */
    protected function resolve( string $entryName, string $tenant, string $sourceTenant ): ?string
    {
        $relativePath = substr( $entryName, strlen( 'media/' ) );

        if( !$relativePath || str_ends_with( $relativePath, '/' ) ) {
            return null;
        }

        if( str_contains( $relativePath, '..' ) || str_starts_with( $relativePath, '/' ) ) {
            throw new \RuntimeException( sprintf( 'Unsafe media path detected: %s', $relativePath ) );
        }

        $targetPath = $tenant !== $sourceTenant
            ? str_replace( 'cms/' . $sourceTenant . '/', 'cms/' . $tenant . '/', $relativePath )
            : $relativePath;

        if( !str_starts_with( $targetPath, 'cms/' . $tenant . '/' ) ) {
            throw new \RuntimeException( sprintf( 'Media path outside tenant scope: %s', $targetPath ) );
        }

        return $targetPath;
    }


    /**
     * Acquires a lock, confirms with the user, and performs the restore.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param array<string, mixed> $manifest Manifest data
     * @param string $tenant Target tenant ID
     * @param string $file Backup filename
     * @return int Command exit code
     */
    protected function restore( \ZipArchive $zip, array $manifest, string $tenant, string $file ): int
    {
        $merge = $this->option( 'merge' );

        if( !$merge && !$this->option( 'force' ) && !$this->option( 'no-interaction' ) )
        {
            if( !$this->confirm( sprintf( 'This will delete all existing data for tenant "%s". Continue?', $tenant ) ) ) {
                return Command::SUCCESS;
            }
        }

        $lock = Cache::lock( 'cms_backup_' . $tenant, 600 );

        if( !$lock->get() )
        {
            $this->warn( 'Another backup/restore operation is in progress for this tenant.' );
            return Command::FAILURE;
        }

        $sourceTenant = $manifest['tenant_id'];
        $trackingFile = $this->trackingFilePath( $tenant );

        try
        {
            if( $this->option( 'media-only' ) )
            {
                $this->restoreMedia( $zip, $tenant, $sourceTenant, $trackingFile );
                RestoreCompleted::dispatch( $tenant, $file, $manifest['counts'] ?? [] );

                $this->info( 'Media restore completed.' );
                return Command::SUCCESS;
            }

            if( !$this->option( 'no-media' ) ) {
                $this->restoreMedia( $zip, $tenant, $sourceTenant, $trackingFile );
            }

            $this->restoreDatabase( $zip, $tenant, $sourceTenant, $merge );
            $this->finalize( $tenant, $file, $manifest['counts'] ?? [] );

            return Command::SUCCESS;
        }
        catch( \Throwable $e )
        {
            $this->cleanupMedia( $trackingFile );
            throw $e;
        }
        finally
        {
            @unlink( $trackingFile );
            $lock->forceRelease();
        }
    }


    /**
     * Restores the database from the ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param bool $merge Whether to merge (upsert) instead of replacing
     */
    protected function restoreDatabase( \ZipArchive $zip, string $tenant, string $sourceTenant, bool $merge ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->info( 'Restoring database...' );

        $columns = $this->classify( $db, $this->discover( $zip, $db ) );

        $db->transaction( function() use ( $zip, $db, $tenant, $sourceTenant, $merge, $columns ) {

            $db->getSchemaBuilder()->disableForeignKeyConstraints();

            try
            {
                if( !$merge ) {
                    $this->cleanupDatabase( $db, $tenant, $columns );
                }

                foreach( $columns as $table => $cols )
                {
                    $entry = $table . '.ndjson';

                    if( $zip->locateName( $entry ) === false ) {
                        continue;
                    }

                    $count = $this->import( $zip, $db, $table, $cols, $entry, $tenant, $sourceTenant, $merge );
                    $this->line( sprintf( '  %s: %d records', $table, $count ), null, 'v' );
                }
            }
            finally
            {
                $db->getSchemaBuilder()->enableForeignKeyConstraints();
            }
        } );
    }


    /**
     * Restores media files from the ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param string $trackingFile Path to tracking file for rollback
     */
    protected function restoreMedia( \ZipArchive $zip, string $tenant, string $sourceTenant, string $trackingFile ): void
    {
        $this->info( 'Restoring media files...' );

        if( !( $fh = fopen( $trackingFile, 'a' ) ) ) {
            throw new \RuntimeException( 'Failed to create tracking file: ' . $trackingFile );
        }

        $storage = Storage::disk( config( 'cms.disk', 'public' ) );
        $count = 0;

        try
        {
            for( $i = 0; $i < $zip->numFiles; $i++ )
            {
                $stat = $zip->statIndex( $i );

                if( !$stat || !str_starts_with( $stat['name'], 'media/' ) ) {
                    continue;
                }

                $targetPath = $this->resolve( $stat['name'], $tenant, $sourceTenant );

                if( !$targetPath || $storage->exists( $targetPath ) ) {
                    continue;
                }

                $stream = $zip->getStream( $stat['name'] );

                if( !$stream ) {
                    continue;
                }

                $storage->writeStream( $targetPath, $stream );

                if( is_resource( $stream ) ) {
                    fclose( $stream );
                }

                fwrite( $fh, $targetPath . "\n" );
                $count++;
            }
        }
        finally
        {
            fclose( $fh );
        }

        $this->line( sprintf( '  %d media files restored', $count ), null, 'v' );
    }


    /**
     * Transforms a row for import: sets tenant, rewrites paths for cross-tenant restores.
     *
     * @param array<string, mixed> $row Filtered record data
     * @param string $table Database table name
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param bool $hasTenant Whether the table has a tenant_id column
     * @return array<string, mixed> Transformed record
     */
    protected function rewrite( array $row, string $table, string $tenant, string $sourceTenant, bool $hasTenant ): array
    {
        if( !$hasTenant ) {
            return $row;
        }

        $row['tenant_id'] = $tenant;

        if( $tenant !== $sourceTenant )
        {
            if( $table === 'cms_files' ) {
                $row = $this->rewritePaths( $row, $sourceTenant, $tenant, ['path', 'previews'] );
            }

            if( $table === 'cms_versions' ) {
                $row = $this->rewritePaths( $row, $sourceTenant, $tenant, ['data', 'aux'] );
            }
        }

        return $row;
    }


    /**
     * Rewrites tenant paths in specific fields of a record.
     *
     * @param array<string, mixed> $row Record data
     * @param string $from Source tenant
     * @param string $to Target tenant
     * @param list<string> $fields Field names to rewrite
     * @return array<string, mixed> Updated record
     */
    protected function rewritePaths( array $row, string $from, string $to, array $fields ): array
    {
        $search = 'cms/' . $from . '/';
        $replace = 'cms/' . $to . '/';

        foreach( $fields as $field )
        {
            if( isset( $row[$field] ) && is_string( $row[$field] ) ) {
                $row[$field] = str_replace( $search, $replace, $row[$field] );
            }
        }

        return $row;
    }


    /**
     * Creates a temporary file path.
     *
     * @param string $prefix Filename prefix
     * @return string Temp file path
     */
    protected function tempFilePath( string $prefix ): string
    {
        return $this->tempdir() . '/' . $prefix . uniqid() . '.zip';
    }


    /**
     * Gets the path for the media tracking file.
     *
     * @param string $tenant Tenant ID
     * @return string Tracking file path
     */
    protected function trackingFilePath( string $tenant ): string
    {
        return $this->tempdir() . '/cms-restore-' . $tenant . '.log';
    }


    /**
     * Verifies backup integrity by checking NDJSON checksums.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param array<string, mixed> $manifest Manifest data
     * @return int Command exit code
     */
    protected function verify( \ZipArchive $zip, array $manifest ): int
    {
        $this->info( 'Verifying backup integrity...' );

        $checksums = $manifest['checksums'] ?? [];
        $valid = true;

        foreach( $checksums as $file => $expectedHash )
        {
            if( !( $stream = $zip->getStream( (string) $file ) ) )
            {
                $this->error( sprintf( '  MISSING: %s', $file ) );
                $valid = false;
                continue;
            }

            $ctx = hash_init( 'sha256' );

            while( !feof( $stream ) )
            {
                if( ( $data = fread( $stream, 65536 ) ) !== false ) {
                    hash_update( $ctx, $data );
                }
            }

            fclose( $stream );

            $hash = hash_final( $ctx );

            if( $hash !== $expectedHash )
            {
                $this->error( sprintf( '  FAILED: %s', $file ) );
                $valid = false;
            }
            else
            {
                $this->line( sprintf( '  OK: %s', $file ), null, 'v' );
            }
        }

        /** @var array<string, int> $counts */
        $counts = $manifest['counts'] ?? [];
        $this->table( ['Table', 'Records'], collect( $counts )->map( fn( $c, $t ) => [$t, $c] )->values()->toArray() );

        if( $valid )
        {
            $this->info( 'Backup integrity verified.' );
            return Command::SUCCESS;
        }

        $this->error( 'Backup integrity check failed.' );
        return Command::FAILURE;
    }


    /**
     * Verifies record counts match the manifest after restore.
     *
     * @param string $tenant Tenant ID
     * @param array<string, int> $counts Expected counts from manifest
     */
    protected function verifyCounts( string $tenant, array $counts ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $schema = $db->getSchemaBuilder();

        foreach( $counts as $table => $expected )
        {
            if( !$schema->hasTable( $table ) || !$schema->hasColumn( $table, 'tenant_id' ) ) {
                continue;
            }

            $actual = $db->table( $table )->where( 'tenant_id', $tenant )->count();

            if( $actual !== $expected ) {
                $this->warn( sprintf( '  Count mismatch for %s: expected %d, got %d', $table, $expected, $actual ) );
            }
        }
    }


    /**
     * Opens and validates a backup ZIP archive.
     *
     * @param string $path Local file path to the ZIP
     * @return \ZipArchive Validated ZIP archive
     */
    protected function zip( string $path ): \ZipArchive
    {
        $zip = new \ZipArchive();

        if( $zip->open( $path ) !== true ) {
            throw new \RuntimeException( 'Failed to open ZIP archive.' );
        }

        try
        {
            $total = 0;

            for( $i = 0; $i < $zip->numFiles; $i++ )
            {
                $stat = $zip->statIndex( $i );

                if( !$stat ) {
                    continue;
                }

                if( str_contains( $stat['name'], '..' ) || str_starts_with( $stat['name'], '/' ) ) {
                    throw new \RuntimeException( sprintf( 'Path traversal detected in ZIP: %s', $stat['name'] ) );
                }

                $total += $stat['size'];

                if( $total > self::MAX_EXTRACTED_SIZE ) {
                    throw new \RuntimeException( 'ZIP archive exceeds maximum extracted size limit' );
                }
            }
        }
        catch( \Throwable $e )
        {
            $zip->close();
            throw $e;
        }

        return $zip;
    }
}
