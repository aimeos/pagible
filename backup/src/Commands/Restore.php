<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Events\RestoreCompleted;
use Aimeos\Cms\Events\RestoreFailed;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
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
        $optDisk = $this->option( 'disk' );
        $disk = is_string( $optDisk ) ? $optDisk : 'local';

        if( $this->option( 'list' ) ) {
            return $this->list( $disk );
        }

        if( $this->option( 'no-media' ) && $this->option( 'media-only' ) )
        {
            $this->error( 'The --no-media and --media-only options cannot be combined.' );
            return Command::FAILURE;
        }

        $file = $this->argument( 'file' );

        if( !$file || !is_string( $file ) )
        {
            $this->error( 'Please specify a backup file. Use --list to see available backups.' );
            return Command::FAILURE;
        }

        $tenant = is_string( $this->option( 'tenant' ) ) ? $this->option( 'tenant' ) : null;
        $path = null;

        try
        {
            $path = $this->path( $disk, $file );
            $zip = $this->zip( $path );

            try
            {
                $manifest = $this->manifest( $zip );
                $tenant = Tenancy::check( (string) ( $tenant ?? $manifest['tenant_id'] ?: Tenancy::value() ) );
                $verified = $this->verify( $zip, $manifest );

                if( $this->option( 'verify' ) || $verified !== Command::SUCCESS ) {
                    return $verified;
                }

                return Tenancy::run(
                    $tenant,
                    fn() => $this->restore( $zip, $manifest, $tenant, $file ),
                );
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
            if( $path && str_contains( basename( $path ), 'cms-restore-' ) ) {
                @unlink( $path );
            }
        }
    }


    /**
     * Adds unique, validated media paths to an archive File entry.
     *
     * @param array<string, array{disk: string, paths: list<string>}> $files
     * @param list<mixed> $values
     */
    protected function addPaths( array &$files, string $id, array $values, string $tenant, string $sourceTenant ): void
    {
        if( !isset( $files[$id] ) ) {
            return;
        }

        foreach( $values as $path )
        {
            if( Utils::normalizePath( $path, $sourceTenant ) === null ) {
                continue;
            }

            $target = $this->resolve( 'media/' . $path, $tenant, $sourceTenant );

            if( !$target ) {
                continue;
            }

            $this->validatePath( $tenant, $id, $target, false );

            if( !in_array( $target, $files[$id]['paths'], true ) ) {
                $files[$id]['paths'][] = $target;
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
     * @param string $tenant Tenant ID owning the storage namespace
     */
    protected function cleanupMedia( string $trackingFile, string $tenant ): void
    {
        if( !file_exists( $trackingFile ) ) {
            return;
        }

        $fh = fopen( $trackingFile, 'r' );

        if( !$fh ) {
            throw new \RuntimeException( 'Failed to open media rollback journal' );
        }

        $error = null;

        while( ( $line = fgets( $fh ) ) !== false )
        {
            $data = json_decode( trim( $line ), true );

            if( !is_array( $data ) ) {
                $error ??= new \RuntimeException( 'Invalid media rollback journal entry' );
                continue;
            }

            $logical = (string) ( $data['disk'] ?? 'public' );
            $path = (string) ( $data['path'] ?? '' );
            $backup = $data['backup'] ?? null;

            try
            {
                if( !in_array( $logical, ['public', 'private'], true )
                    || File::owner( $tenant, $path ) === null ) {
                    throw new \RuntimeException( 'Invalid media rollback journal path' );
                }

                $storage = Storage::disk( File::diskName( $logical ) );

                if( is_string( $backup ) )
                {
                    $expected = $this->rollbackDir( $trackingFile ) . '/'
                        . hash( 'sha256', $logical . "\0" . $path ) . '.media';

                    if( $backup !== $expected || !( $stream = fopen( $backup, 'r' ) ) ) {
                        throw new \RuntimeException( sprintf( 'Missing media rollback copy for "%s"', $path ) );
                    }

                    try {
                        $written = $storage->writeStream( $path, $stream );
                    } finally {
                        fclose( $stream );
                    }

                    $size = filesize( $backup );

                    if( !$written || $size === false || !$storage->exists( $path )
                        || $storage->size( $path ) !== $size ) {
                        throw new \RuntimeException( sprintf( 'Failed to restore media rollback copy for "%s"', $path ) );
                    }
                }
                elseif( $backup === null )
                {
                    $storage->delete( $path );

                    if( $storage->exists( $path ) ) {
                        throw new \RuntimeException( sprintf( 'Failed to remove restored media path "%s"', $path ) );
                    }
                }
                else {
                    throw new \RuntimeException( 'Invalid media rollback journal entry' );
                }
            }
            catch( \Throwable $e )
            {
                report( $e );
                $error ??= $e;
            }
        }

        fclose( $fh );

        if( $error ) {
            throw new \RuntimeException( $error->getMessage(), 0, $error );
        }
    }


    /**
     * Confirms that a restore mode which cannot change media preserves live disk ownership.
     *
     * @param array<string, array{disk: string, paths: list<string>}> $files
     */
    protected function confirmDisks( string $tenant, array $files, bool $required, bool $media ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $current = [];

        foreach( array_chunk( array_keys( $files ), 500 ) as $ids )
        {
            $rows = $db->table( 'cms_files' )->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $ids )->select( 'id', 'disk' )->get();

            foreach( $rows as $row ) {
                $current[(string) $row->id] = (string) $row->disk;
            }
        }

        $currentIds = array_keys( $current );
        usort( $currentIds, fn( string $a, string $b ) => strcasecmp( $a, $b ) );

        foreach( $files as $id => $file )
        {
            if( $required && !$file['paths'] ) {
                continue;
            }

            $disk = $current[$id] ?? $this->findDisk( $current, $currentIds, $id );

            if( $disk === null )
            {
                if( $required ) {
                    throw new \RuntimeException( sprintf( 'File "%s" does not exist for media-only restore', $id ) );
                }

                continue;
            }

            if( $disk !== $file['disk'] ) {
                throw new \RuntimeException( sprintf(
                    'File "%s" uses disk "%s", backup expects "%s"',
                    $id,
                    $disk,
                    $file['disk'],
                ) );
            }
        }

        if( !$media )
        {
            foreach( $files as $file )
            {
                $other = $file['disk'] === 'public' ? 'private' : 'public';
                $storage = Storage::disk( File::diskName( $other ) );

                foreach( $file['paths'] as $path )
                {
                    if( $storage->exists( $path ) ) {
                        throw new \RuntimeException( sprintf(
                            'Media path "%s" still exists on disk "%s"; restore it without --no-media',
                            $path,
                            $other,
                        ) );
                    }
                }
            }
        }
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
     * Finds a File disk by case-insensitive UUID comparison without rewriting the UUID.
     *
     * @param array<string, string> $current File disks keyed by database-returned UUID
     * @param list<string> $ids Case-insensitively sorted database-returned UUIDs
     */
    protected function findDisk( array $current, array $ids, string $id ): ?string
    {
        $low = 0;
        $high = count( $ids ) - 1;

        while( $low <= $high )
        {
            $mid = intdiv( $low + $high, 2 );
            $cmp = strcasecmp( $ids[$mid], $id );

            if( $cmp === 0 ) {
                return $current[$ids[$mid]];
            }

            if( $cmp < 0 ) {
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return null;
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
     * Rejects cross-tenant restores whose globally unique IDs belong to another tenant.
     */
    protected function guardIds( \ZipArchive $zip, string $tenant, string $sourceTenant ): void
    {
        if( $tenant === $sourceTenant ) {
            return;
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $columns = $this->classify( $db, $this->discover( $zip, $db ) );

        foreach( $columns as $table => $cols )
        {
            if( !in_array( 'id', $cols, true ) || !in_array( 'tenant_id', $cols, true )
                || !( $stream = $zip->getStream( $table . '.ndjson' ) ) ) {
                continue;
            }

            $ids = [];

            try
            {
                while( ( $line = fgets( $stream, self::MAX_LINE_LENGTH ) ) !== false )
                {
                    $row = json_decode( trim( $line ), true );
                    $id = is_array( $row ) ? $row['id'] ?? null : null;

                    if( is_string( $id ) && $id !== '' ) {
                        $ids[] = $id;
                    }

                    if( count( $ids ) >= 250 )
                    {
                        $this->guardIdChunk( $db, $table, $ids, $tenant );
                        $ids = [];
                    }
                }

                if( $ids ) {
                    $this->guardIdChunk( $db, $table, $ids, $tenant );
                }
            }
            finally {
                fclose( $stream );
            }
        }
    }


    /**
     * Rejects one archived ID chunk already owned by another tenant.
     *
     * @param list<string> $ids
     */
    protected function guardIdChunk( Connection $db, string $table, array $ids, string $tenant ): void
    {
        if( $db->table( $table )->whereIn( 'id', $ids )
            ->where( 'tenant_id', '<>', $tenant )->exists() ) {
            throw new \RuntimeException( sprintf(
                'Cross-tenant restore conflicts with existing rows in table "%s"',
                $table,
            ) );
        }
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
     * @param array<string, array{disk: string, paths: list<string>}> $files Archive File catalog
     * @return int Number of records imported
     */
    protected function import( \ZipArchive $zip, Connection $db, string $table, array $columns,
        string $entry, string $tenant, string $sourceTenant, bool $merge, array $files ): int
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

            $row = $this->rewrite(
                array_intersect_key( $row, $allowed ),
                $table,
                $tenant,
                $sourceTenant,
                $hasTenant,
                $files,
            );
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

        if( $merge )
        {
            /** @var non-empty-list<non-empty-string> $columns */
            $columns = array_keys( $rows[0] ?? [] );
            $updateColumns = $hasTenant ? array_values( array_diff( $columns, ['id'] ) ) : $columns;
            $query->upsert( $rows, $hasTenant ? ['id'] : $columns, $updateColumns );
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
        $optTenant = $this->option( 'tenant' );
        $tenant = is_string( $optTenant ) ? $optTenant : '';
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

        if( (string) $manifest['format_version'] !== '3' ) {
            throw new \RuntimeException( sprintf(
                'Unsupported backup format version "%s"',
                (string) $manifest['format_version'],
            ) );
        }

        $signature = $manifest['signature'] ?? null;

        if( !is_string( $signature ) || !hash_equals( $this->sign( $manifest ), $signature ) ) {
            throw new \RuntimeException( 'Backup manifest signature is invalid' );
        }

        Tenancy::check( (string) $manifest['tenant_id'] );

        return $manifest;
    }


    /**
     * Returns the archive's File owners, logical disks, and catalog-owned media paths.
     *
     * @return array<string, array{disk: string, paths: list<string>}>
     */
    protected function mediaFiles( \ZipArchive $zip, string $tenant, string $sourceTenant ): array
    {
        $files = [];

        if( $stream = $zip->getStream( 'cms_files.ndjson' ) )
        {
            try
            {
                while( ( $line = fgets( $stream, self::MAX_LINE_LENGTH ) ) !== false )
                {
                    $row = json_decode( trim( (string) $line ), true );

                    if( !is_array( $row ) ) {
                        continue;
                    }

                    $id = (string) ( $row['id'] ?? '' );
                    $disk = (string) ( $row['disk'] ?? 'public' );

                    if( !in_array( $disk, ['public', 'private'], true ) ) {
                        throw new \RuntimeException( sprintf( 'Invalid file disk "%s"', $disk ) );
                    }

                    $files[$id] = ['disk' => $disk, 'paths' => []];
                    $previews = json_decode( (string) ( $row['previews'] ?? '{}' ), true );
                    $this->addPaths( $files, $id, [
                        $row['path'] ?? null,
                        ...array_values( is_array( $previews ) ? $previews : [] ),
                    ], $tenant, $sourceTenant );
                }
            }
            finally
            {
                fclose( $stream );
            }
        }

        if( $stream = $zip->getStream( 'cms_versions.ndjson' ) )
        {
            try
            {
                while( ( $line = fgets( $stream, self::MAX_LINE_LENGTH ) ) !== false )
                {
                    $row = json_decode( trim( (string) $line ), true );

                    if( !is_array( $row ) || ( $row['versionable_type'] ?? null ) !== File::class ) {
                        continue;
                    }

                    $id = (string) ( $row['versionable_id'] ?? '' );

                    if( !isset( $files[$id] ) ) {
                        continue;
                    }

                    $data = json_decode( (string) ( $row['data'] ?? '{}' ), true );
                    $data = is_array( $data ) ? $data : [];
                    $previews = is_array( $data['previews'] ?? null ) ? $data['previews'] : [];
                    $this->addPaths( $files, $id, [
                        $data['path'] ?? null,
                        ...array_values( $previews ),
                    ], $tenant, $sourceTenant );
                }
            }
            finally
            {
                fclose( $stream );
            }
        }

        return $files;
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
        $stream = null;
        $out = null;
        $complete = false;

        try
        {
            $size = $storage->size( $file );
            $stream = $storage->readStream( $file );

            if( !$stream ) {
                throw new \RuntimeException( 'Failed to read backup file from disk' );
            }

            $out = fopen( $tmpPath, 'wb' );

            if( !$out ) {
                throw new \RuntimeException( 'Failed to create temporary file for restore' );
            }

            $written = stream_copy_to_stream( $stream, $out );
            $flushed = fflush( $out );

            if( $written === false || $written !== $size || !$flushed
                || filesize( $tmpPath ) !== $size ) {
                throw new \RuntimeException( 'Failed to verify temporary backup file' );
            }

            $complete = true;
            return $tmpPath;
        }
        finally
        {
            if( is_resource( $out ) ) {
                fclose( $out );
            }
            if( is_resource( $stream ) ) {
                fclose( $stream );
            }
            if( !$complete ) {
                @unlink( $tmpPath );
            }
        }
    }


    /**
     * Removes stale same-path copies from the opposite logical disk.
     *
     * @param array<string, array{disk: string, paths: list<string>}> $files
     */
    protected function reconcileMedia( string $tenant, array $files, string $trackingFile ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        if( !( $fh = fopen( $trackingFile, 'a' ) ) ) {
            throw new \RuntimeException( 'Failed to open tracking file: ' . $trackingFile );
        }

        @chmod( $trackingFile, 0600 );

        try
        {
            foreach( $files as $id => $file )
            {
                if( !$file['paths'] ) {
                    continue;
                }

                Utils::fileLock( $tenant, $id, function() use (
                    $db, $fh, $file, $id, $tenant, $trackingFile
                ) {
                    $disk = $db->table( 'cms_files' )->where( 'tenant_id', $tenant )
                        ->where( 'id', $id )->value( 'disk' );

                    if( $disk !== $file['disk'] ) {
                        throw new \RuntimeException( sprintf(
                            'File "%s" uses disk "%s", backup expects "%s"',
                            $id,
                            $disk ?? 'missing',
                            $file['disk'],
                        ) );
                    }

                    $target = Storage::disk( File::diskName( $file['disk'] ) );
                    $other = $file['disk'] === 'public' ? 'private' : 'public';
                    $source = Storage::disk( File::diskName( $other ) );

                    foreach( $file['paths'] as $path )
                    {
                        if( !$target->exists( $path ) )
                        {
                            if( $source->exists( $path ) ) {
                                throw new \RuntimeException( sprintf(
                                    'Media path "%s" exists only on disk "%s"',
                                    $path,
                                    $other,
                                ) );
                            }

                            continue;
                        }

                        if( $source->exists( $path ) )
                        {
                            $this->trackMedia( $fh, $trackingFile, $other, $path );
                            $source->delete( $path );

                            if( Storage::disk( File::diskName( $other ) )->exists( $path ) ) {
                                throw new \RuntimeException( sprintf(
                                    'Failed to remove media path "%s" from disk "%s"',
                                    $path,
                                    $other,
                                ) );
                            }
                        }
                    }
                } );
            }
        }
        finally
        {
            fclose( $fh );
        }
    }


    /**
     * Resolves a media entry path to the target storage path, with tenant rewriting and validation.
     *
     * @param string $entryName ZIP entry name without its disk (e.g. "media/cms/tenant/file.jpg")
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

        $sourcePrefix = 'cms/' . ( $sourceTenant !== '' ? $sourceTenant . '/' : '' );
        $targetPrefix = 'cms/' . ( $tenant !== '' ? $tenant . '/' : '' );

        $targetPath = $sourcePrefix !== $targetPrefix
            ? str_replace( $sourcePrefix, $targetPrefix, $relativePath )
            : $relativePath;

        if( !str_starts_with( $targetPath, $targetPrefix ) ) {
            throw new \RuntimeException( sprintf( 'Media path outside tenant scope: %s', $targetPath ) );
        }

        // The archive is untrusted: neutralize executable/active-content extensions the same way
        // uploads do (File::filename), so a crafted backup can't drop e.g. .php/.html into the disk.
        if( Utils::extension( pathinfo( $targetPath, PATHINFO_EXTENSION ) ) === 'bin' ) {
            $targetPath .= '.bin';
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
        $merge = (bool) $this->option( 'merge' );

        if( !$merge && !$this->option( 'force' ) && !$this->option( 'no-interaction' ) )
        {
            if( !$this->confirm( sprintf( 'This will delete all existing data for tenant "%s". Continue?', $tenant ) ) ) {
                return Command::SUCCESS;
            }
        }

        try
        {
            $mediaOnly = Utils::storageLock( $tenant, function() use (
                $manifest, $merge, $tenant, $zip
            ) {
                $sourceTenant = $manifest['tenant_id'];
                $trackingFile = $this->trackingFilePath( $tenant );
                $files = $this->mediaFiles( $zip, $tenant, $sourceTenant );
                $keepMedia = false;
                $removeTracking = false;

                if( !$this->option( 'media-only' ) ) {
                    $this->guardIds( $zip, $tenant, (string) $sourceTenant );
                }

                try
                {
                    if( $this->option( 'media-only' ) )
                    {
                        $this->confirmDisks( $tenant, $files, true, true );
                        $this->restoreMedia( $zip, $tenant, $sourceTenant, $trackingFile, $files );
                        $this->validateMedia( $files );
                        $this->reconcileMedia( $tenant, $files, $trackingFile );
                        $keepMedia = true;
                        return true;
                    }

                    $noMedia = (bool) $this->option( 'no-media' );

                    if( $noMedia ) {
                        $this->confirmDisks( $tenant, $files, false, false );
                    } else {
                        $this->restoreMedia( $zip, $tenant, $sourceTenant, $trackingFile, $files );
                        $this->validateMedia( $files );
                    }

                    $after = $noMedia ? null
                        : fn() => $this->reconcileMedia( $tenant, $files, $trackingFile );

                    $this->restoreDatabase( $zip, $tenant, $sourceTenant, $merge, $files, $after );
                    $keepMedia = true;

                    return false;
                }
                catch( \Throwable $e )
                {
                    if( !$keepMedia )
                    {
                        try {
                            $this->cleanupMedia( $trackingFile, $tenant );
                            $removeTracking = true;
                        } catch( \Throwable $rollback ) {
                            throw new \RuntimeException( sprintf(
                                'Media rollback failed; journal preserved at "%s": %s',
                                $trackingFile,
                                $rollback->getMessage(),
                            ), 0, $e );
                        }
                    }

                    throw $e;
                }
                finally
                {
                    if( $keepMedia || $removeTracking ) {
                        $this->removeTracking( $trackingFile );
                    }
                }
            }, 0 );
        }
        catch( LockTimeoutException )
        {
            $this->warn( 'Another backup/restore or media operation is in progress for this tenant.' );
            return Command::FAILURE;
        }

        if( $mediaOnly )
        {
            RestoreCompleted::dispatch( $tenant, $file, $manifest['counts'] ?? [] );
            $this->info( 'Media restore completed.' );
        }
        else {
            $this->finalize( $tenant, $file, $manifest['counts'] ?? [] );
        }

        return Command::SUCCESS;
    }


    /**
     * Restores the database from the ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param bool $merge Whether to merge (upsert) instead of replacing
     * @param array<string, array{disk: string, paths: list<string>}> $files Archive File catalog
     * @param \Closure|null $after Work to complete before the database transaction commits
     */
    protected function restoreDatabase( \ZipArchive $zip, string $tenant, string $sourceTenant,
        bool $merge, array $files, ?\Closure $after = null ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->info( 'Restoring database...' );

        $columns = $this->classify( $db, $this->discover( $zip, $db ) );

        // Sort entity tables (with id) before pivot tables, shorter names first (parents before children)
        uksort( $columns, function( string $a, string $b ) use ( $columns ) {
            $aHasId = in_array( 'id', $columns[$a] );
            $bHasId = in_array( 'id', $columns[$b] );

            return $aHasId === $bHasId ? ( strlen( $b ) <=> strlen( $a ) ?: strcmp( $a, $b ) ) : ( $bHasId <=> $aHasId );
        } );

        $db->transaction( function() use ( $zip, $db, $tenant, $sourceTenant, $merge, $columns, $files, $after ) {

            if( !$merge ) {
                $this->cleanupDatabase( $db, $tenant, $columns );
            }

            foreach( $columns as $table => $cols )
            {
                $entry = $table . '.ndjson';

                if( $zip->locateName( $entry ) === false ) {
                    continue;
                }

                $count = $this->import( $zip, $db, $table, $cols, $entry, $tenant, $sourceTenant, $merge, $files );
                $this->line( sprintf( '  %s: %d records', $table, $count ), null, 'v' );
            }

            $after?->__invoke();
        } );
    }


    /**
     * Restores media files from the ZIP archive.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param string $trackingFile Path to tracking file for rollback
     * @param array<string, array{disk: string, paths: list<string>}> $files
     */
    protected function restoreMedia( \ZipArchive $zip, string $tenant, string $sourceTenant,
        string $trackingFile, array $files ): void
    {
        $this->info( 'Restoring media files...' );

        if( !( $fh = fopen( $trackingFile, 'a' ) ) ) {
            throw new \RuntimeException( 'Failed to create tracking file: ' . $trackingFile );
        }

        @chmod( $trackingFile, 0600 );

        $count = 0;
        $catalog = [];

        foreach( $files as $file ) {
            $catalog += array_fill_keys( $file['paths'], $file['disk'] );
        }

        try
        {
            for( $i = 0; $i < $zip->numFiles; $i++ )
            {
                $stat = $zip->statIndex( $i );

                if( !$stat || !str_starts_with( $stat['name'], 'media/' ) ) {
                    continue;
                }

                $entry = substr( $stat['name'], strlen( 'media/' ) );

                if( !preg_match( '#^(public|private)/(.*)$#', $entry, $matches ) ) {
                    continue;
                }

                $logical = $matches[1];
                $entry = $matches[2];
                $targetPath = $this->resolve( 'media/' . $entry, $tenant, $sourceTenant );

                if( !$targetPath ) {
                    continue;
                }

                if( !isset( $catalog[$targetPath] ) ) {
                    throw new \RuntimeException( sprintf( 'Media path is not referenced by the file catalog: "%s"', $targetPath ) );
                }

                if( $catalog[$targetPath] !== $logical ) {
                    throw new \RuntimeException( sprintf(
                        'Media disk "%s" does not match catalog disk "%s" for path "%s"',
                        $logical,
                        $catalog[$targetPath],
                        $targetPath,
                    ) );
                }

                $storage = Storage::disk( File::diskName( $logical ) );

                $stream = $zip->getStream( $stat['name'] );

                if( !$stream ) {
                    throw new \RuntimeException( sprintf( 'Failed to read media entry "%s"', $stat['name'] ) );
                }

                try {
                    $this->trackMedia( $fh, $trackingFile, $logical, $targetPath );
                } catch( \Throwable $e ) {
                    fclose( $stream );
                    throw $e;
                }

                try
                {
                    // SVGs from the untrusted archive must be sanitized (they can carry scripts), the
                    // same way uploads are sanitized in File::addFile.
                    if( str_starts_with( strtolower( pathinfo( $targetPath, PATHINFO_EXTENSION ) ), 'svg' ) )
                    {
                        $content = stream_get_contents( $stream );
                        $clean = $content === false ? null : Utils::cleanSvg( $content );
                        $written = $clean !== null && $storage->put( $targetPath, $clean );
                        $size = $clean === null ? null : strlen( $clean );
                    }
                    else {
                        $written = $storage->writeStream( $targetPath, $stream );
                        $size = (int) $stat['size'];
                    }
                }
                finally {
                    if( is_resource( $stream ) ) {
                        fclose( $stream );
                    }
                }

                if( !$written || $size === null || !$storage->exists( $targetPath )
                    || $storage->size( $targetPath ) !== $size ) {
                    throw new \RuntimeException( sprintf( 'Failed to restore media path "%s"', $targetPath ) );
                }

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
     * @param array<string, array{disk: string, paths: list<string>}> $files Archive File catalog
     * @return array<string, mixed> Transformed record
     */
    protected function rewrite( array $row, string $table, string $tenant, string $sourceTenant,
        bool $hasTenant, array $files ): array
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

        if( $table === 'cms_files' ) {
            $this->validateFilePaths( $row, $tenant );
        } elseif( $table === 'cms_versions' && ( $row['versionable_type'] ?? null ) === File::class ) {
            $this->validateVersionPaths( $row, $tenant, $files );
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
        $search = 'cms/' . ( $from !== '' ? $from . '/' : '' );
        $replace = 'cms/' . ( $to !== '' ? $to . '/' : '' );

        foreach( $fields as $field )
        {
            if( isset( $row[$field] ) && is_string( $row[$field] ) ) {
                $row[$field] = str_replace( $search, $replace, $row[$field] );
            }
        }

        return $row;
    }


    /**
     * Removes a completed or successfully rolled-back media journal.
     */
    protected function removeTracking( string $trackingFile ): void
    {
        $dir = $this->rollbackDir( $trackingFile );

        if( is_dir( $dir ) )
        {
            $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO;

            foreach( new \FilesystemIterator( $dir, $flags ) as $file ) {
                if( $file instanceof \SplFileInfo ) {
                    @unlink( $file->getPathname() );
                } else {
                    @unlink( $dir . '/' . $file );
                }
            }

            @rmdir( $dir );
        }

        @unlink( $trackingFile );
    }


    /**
     * Returns the private local directory used for original media copies.
     */
    protected function rollbackDir( string $trackingFile ): string
    {
        return $trackingFile . '.d';
    }


    /**
     * Creates a temporary file path.
     *
     * @param string $prefix Filename prefix
     * @return string Temp file path
     */
    protected function tempFilePath( string $prefix ): string
    {
        $path = tempnam( $this->tempdir(), $prefix );

        if( $path === false ) {
            throw new \RuntimeException( 'Failed to create temporary restore file' );
        }

        @chmod( $path, 0600 );
        return $path;
    }


    /**
     * Gets the path for the media tracking file.
     *
     * @param string $tenant Tenant ID
     * @return string Tracking file path
     */
    protected function trackingFilePath( string $tenant ): string
    {
        $name = $tenant !== '' ? $tenant : 'default';
        return $this->tempdir() . '/cms-restore-' . $name . '-' . bin2hex( random_bytes( 8 ) ) . '.log';
    }


    /**
     * Journals the current state of one media path before it is changed.
     *
     * @param resource $fh Open rollback journal
     */
    protected function trackMedia( mixed $fh, string $trackingFile, string $logical, string $path ): void
    {
        $dir = $this->rollbackDir( $trackingFile );

        if( !is_dir( $dir ) && !mkdir( $dir, 0700, true ) && !is_dir( $dir ) ) {
            throw new \RuntimeException( 'Failed to create media rollback directory' );
        }

        $key = hash( 'sha256', $logical . "\0" . $path );
        $marker = $dir . '/' . $key . '.tracked';

        if( file_exists( $marker ) ) {
            return;
        }

        $storage = Storage::disk( File::diskName( $logical ) );
        $backup = null;

        if( $storage->exists( $path ) )
        {
            $size = $storage->size( $path );
            $backup = $dir . '/' . $key . '.media';
            $stream = $storage->readStream( $path );

            if( !$stream || !( $out = fopen( $backup, 'x+b' ) ) ) {
                if( is_resource( $stream ) ) {
                    fclose( $stream );
                }
                throw new \RuntimeException( sprintf( 'Failed to preserve media path "%s"', $path ) );
            }

            try {
                $written = stream_copy_to_stream( $stream, $out );
                $flushed = fflush( $out );
            } finally {
                fclose( $stream );
                fclose( $out );
            }

            if( $written === false || $written !== $size || !$flushed
                || filesize( $backup ) !== $size ) {
                @unlink( $backup );
                throw new \RuntimeException( sprintf( 'Failed to preserve media path "%s"', $path ) );
            }

            @chmod( $backup, 0600 );
        }

        $entry = json_encode( array_filter( [
            'disk' => $logical,
            'path' => $path,
            'backup' => $backup,
        ], fn( mixed $value ) => $value !== null ), JSON_THROW_ON_ERROR ) . "\n";

        if( fwrite( $fh, $entry ) === false || !fflush( $fh )
            || file_put_contents( $marker, '' ) === false ) {
            throw new \RuntimeException( 'Failed to track restored media path' );
        }
    }


    /**
     * Rejects a restore which would leave the database pointing at an opposite-disk copy.
     *
     * @param array<string, array{disk: string, paths: list<string>}> $files
     */
    protected function validateMedia( array $files ): void
    {
        foreach( $files as $file )
        {
            $target = Storage::disk( File::diskName( $file['disk'] ) );
            $other = $file['disk'] === 'public' ? 'private' : 'public';
            $source = Storage::disk( File::diskName( $other ) );

            foreach( $file['paths'] as $path )
            {
                if( !$target->exists( $path ) )
                {
                    if( $source->exists( $path ) ) {
                        throw new \RuntimeException( sprintf(
                            'Media path "%s" exists only on disk "%s"',
                            $path,
                            $other,
                        ) );
                    }

                    throw new \RuntimeException( sprintf(
                        'Media path "%s" is missing from disk "%s"',
                        $path,
                        $file['disk'],
                    ) );
                }
            }
        }
    }


    /**
     * Validates restored File paths and the logical disk.
     *
     * @param array<string, mixed> $row File record
     */
    protected function validateFilePaths( array $row, string $tenant ): void
    {
        $disk = (string) ( $row['disk'] ?? 'public' );

        if( !in_array( $disk, ['public', 'private'], true ) ) {
            throw new \RuntimeException( sprintf( 'Invalid file disk "%s"', $disk ) );
        }

        $previews = json_decode( (string) ( $row['previews'] ?? '{}' ), true );
        $paths = [$row['path'] ?? null, ...array_values( is_array( $previews ) ? $previews : [] )];

        foreach( $paths as $path ) {
            $this->validatePath( $tenant, (string) ( $row['id'] ?? '' ), $path, $disk === 'public' );
        }
    }


    /**
     * Validates one restored managed path.
     */
    protected function validatePath( string $tenant, string $id, mixed $path, bool $remote = true ): void
    {
        if( $path === null || $path === '' ) {
            return;
        }

        if( $remote && is_string( $path ) && str_starts_with( $path, 'http' )
            && Utils::isValidUrl( $path, false ) ) {
            return;
        }

        if( !File::owns( $tenant, $id, $path ) ) {
            throw new \RuntimeException( sprintf( 'File path is outside UUID directory "%s"', $id ) );
        }
    }


    /**
     * Validates paths stored in a restored File version.
     *
     * @param array<string, mixed> $row Version record
     * @param array<string, array{disk: string, paths: list<string>}> $files Archive File catalog
     */
    protected function validateVersionPaths( array $row, string $tenant, array $files ): void
    {
        $id = (string) ( $row['versionable_id'] ?? '' );
        $disk = $files[$id]['disk'] ?? null;

        if( !$disk ) {
            throw new \RuntimeException( sprintf( 'File version references unknown file "%s"', $id ) );
        }

        $data = json_decode( (string) ( $row['data'] ?? '{}' ), true );
        $data = is_array( $data ) ? $data : [];
        $previews = is_array( $data['previews'] ?? null ) ? $data['previews'] : [];
        $paths = [$data['path'] ?? null, ...array_values( $previews )];

        foreach( $paths as $path ) {
            $this->validatePath( $tenant, $id, $path, $disk === 'public' );
        }
    }


    /**
     * Verifies all archived database and media files against the signed manifest.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param array<string, mixed> $manifest Manifest data
     * @return int Command exit code
     */
    protected function verify( \ZipArchive $zip, array $manifest ): int
    {
        $this->info( 'Verifying backup integrity...' );

        $checksums = $manifest['checksums'] ?? null;
        $valid = true;

        if( !is_array( $checksums ) ) {
            throw new \RuntimeException( 'Invalid backup checksums' );
        }

        foreach( $checksums as $file => $expectedHash )
        {
            if( !is_string( $file ) || !is_string( $expectedHash )
                || !preg_match( '/^[a-f0-9]{64}$/', $expectedHash ) ) {
                throw new \RuntimeException( 'Invalid backup checksum entry' );
            }

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

        for( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $stat = $zip->statIndex( $i );
            $name = $stat['name'] ?? '';

            if( $name === '' || $name === 'manifest.json' || str_ends_with( $name, '/' ) ) {
                continue;
            }

            if( !array_key_exists( $name, $checksums ) ) {
                $this->error( sprintf( '  UNCHECKED: %s', $name ) );
                $valid = false;
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
            $names = [];
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

                if( isset( $names[$stat['name']] ) ) {
                    throw new \RuntimeException( sprintf( 'Duplicate ZIP entry detected: %s', $stat['name'] ) );
                }

                $names[$stat['name']] = true;
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
