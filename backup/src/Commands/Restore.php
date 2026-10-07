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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;


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
        {--force : Skip confirmation prompts and accept backups from other installations}';

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

            $target = $this->resolve( $path, $tenant, $sourceTenant );

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
     * Returns the validated logical disk of an archived File row.
     *
     * @param array<string, mixed> $row File record
     * @return string Logical disk name
     */
    protected function checkDisk( array $row ): string
    {
        $disk = (string) ( $row['disk'] ?? 'public' );

        if( !in_array( $disk, ['public', 'private'], true ) ) {
            throw new \RuntimeException( sprintf( 'Invalid file disk "%s"', $disk ) );
        }

        return $disk;
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
                    $expected = $trackingFile . '.d/'
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

            // database drivers may return UUIDs in a different case (e.g. SQL Server)
            foreach( $rows as $row ) {
                $current[strtolower( (string) $row->id )] = (string) $row->disk;
            }
        }

        foreach( $files as $id => $file )
        {
            if( $required && !$file['paths'] ) {
                continue;
            }

            $disk = $current[strtolower( $id )] ?? null;

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
                $other = self::other( $file['disk'] );
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
        $this->table( ['Table', 'Expected'], array_map( null, array_keys( $counts ), $counts ) );
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
     *
     * @param \ZipArchive $zip ZIP archive
     * @param Connection $db Database connection
     * @param array<string, list<string>> $columns Table name => column names
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     */
    protected function guardIds( \ZipArchive $zip, Connection $db, array $columns, string $tenant, string $sourceTenant ): void
    {
        if( $tenant === $sourceTenant ) {
            return;
        }

        foreach( $columns as $table => $cols )
        {
            if( !in_array( 'id', $cols, true ) || !in_array( 'tenant_id', $cols, true ) ) {
                continue;
            }

            LazyCollection::make( fn() => $this->rows( $zip, $table . '.ndjson' ) )
                ->map( fn( array $row ) => $row['id'] ?? null )
                ->filter( fn( mixed $id ) => is_string( $id ) && $id !== '' )
                ->chunk( 250 )
                ->each( function( LazyCollection $ids ) use ( $db, $table, $tenant ) {
                    if( $db->table( $table )->whereIn( 'id', $ids->all() )
                        ->where( 'tenant_id', '<>', $tenant )->exists() ) {
                        throw new \RuntimeException( sprintf(
                            'Cross-tenant restore conflicts with existing rows in table "%s"',
                            $table,
                        ) );
                    }
                } );
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
        $count = 0;
        $allowed = array_flip( $columns );
        $hasTenant = isset( $allowed['tenant_id'] );

        LazyCollection::make( fn() => $this->rows( $zip, $entry ) )
            ->map( fn( array $row ) => $this->rewrite(
                array_intersect_key( $row, $allowed ), $table, $tenant, $sourceTenant, $hasTenant, $files
            ) )
            ->chunk( 50 )
            ->each( function( LazyCollection $rows ) use ( $db, $table, $merge, $hasTenant, &$count ) {
                $rows = array_values( $rows->all() );

                if( $merge )
                {
                    /** @var non-empty-list<non-empty-string> $cols */
                    $cols = array_keys( $rows[0] ?? [] );
                    $update = $hasTenant ? array_values( array_diff( $cols, ['id'] ) ) : $cols;
                    $db->table( $table )->upsert( $rows, $hasTenant ? ['id'] : $cols, $update );
                }
                else
                {
                    $db->table( $table )->insert( $rows );
                }

                $count += count( $rows );
            } );

        return $count;
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
        $files = $this->backups( $storage, is_string( $optTenant ) ? $optTenant : '' );

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

        // backups of other installations are signed with a different APP_KEY
        if( !is_string( $signature ) || !hash_equals( $this->sign( $manifest ), $signature ) )
        {
            if( !$this->option( 'force' ) ) {
                throw new \RuntimeException( 'Backup manifest signature is invalid, use --force for backups of other installations' );
            }

            $this->warn( 'Backup manifest signature is invalid or from another installation, restoring unauthenticated backup' );
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

        foreach( $this->rows( $zip, 'cms_files.ndjson' ) as $row )
        {
            $id = (string) ( $row['id'] ?? '' );
            $disk = $this->checkDisk( $row );

            $files[$id] = ['disk' => $disk, 'paths' => []];
            $this->addPaths( $files, $id, self::filePaths( $row ), $tenant, $sourceTenant );
        }

        foreach( $this->rows( $zip, 'cms_versions.ndjson' ) as $row )
        {
            $id = (string) ( $row['versionable_id'] ?? '' );

            if( ( $row['versionable_type'] ?? null ) === File::class && isset( $files[$id] ) ) {
                $this->addPaths( $files, $id, self::versionPaths( $row ), $tenant, $sourceTenant );
            }
        }

        return $files;
    }


    /**
     * Moves a managed path from the source to the target tenant directory, other values are kept.
     */
    protected static function move( mixed $path, string $from, string $to ): mixed
    {
        $prefix = self::prefix( $from );

        return is_string( $path ) && str_starts_with( $path, $prefix )
            ? self::prefix( $to ) . substr( $path, strlen( $prefix ) )
            : $path;
    }


    /**
     * Returns the opposite logical disk.
     */
    protected static function other( string $disk ): string
    {
        return $disk === 'public' ? 'private' : 'public';
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
        $complete = false;

        try
        {
            $size = $storage->size( $file );
            $stream = $storage->readStream( $file );

            if( !$stream ) {
                throw new \RuntimeException( 'Failed to read backup file from disk' );
            }

            $written = file_put_contents( $tmpPath, $stream );

            if( $written !== $size || filesize( $tmpPath ) !== $size ) {
                throw new \RuntimeException( 'Failed to verify temporary backup file' );
            }

            $complete = true;
            return $tmpPath;
        }
        finally
        {
            if( is_resource( $stream ) ) {
                fclose( $stream );
            }
            if( !$complete ) {
                @unlink( $tmpPath );
            }
        }
    }


    /**
     * Returns the storage path prefix of the tenant.
     */
    protected static function prefix( string $tenant ): string
    {
        return 'cms/' . ( $tenant !== '' ? $tenant . '/' : '' );
    }


    /**
     * Removes stale same-path copies from the opposite logical disk.
     *
     * @param array<string, array{disk: string, paths: list<string>}> $files
     */
    protected function reconcileMedia( string $tenant, array $files, string $trackingFile ): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        foreach( $files as $id => $file )
        {
            if( !$file['paths'] ) {
                continue;
            }

            Utils::fileLock( $tenant, $id, function() use ( $db, $file, $id, $tenant, $trackingFile ) {
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
                $other = self::other( $file['disk'] );
                $source = Storage::disk( File::diskName( $other ) );

                // validateMedia() already rejected paths which exist only on the other disk
                foreach( $file['paths'] as $path )
                {
                    if( !$target->exists( $path ) || !$source->exists( $path ) ) {
                        continue;
                    }

                    $this->trackMedia( $trackingFile, $other, $path );
                    $source->delete( $path );

                    // fetched again because PHPStan treats exists() on the same instance as pure
                    if( Storage::disk( File::diskName( $other ) )->exists( $path ) ) {
                        throw new \RuntimeException( sprintf(
                            'Failed to remove media path "%s" from disk "%s"',
                            $path,
                            $other,
                        ) );
                    }
                }
            } );
        }
    }


    /**
     * Resolves a media entry path to the target storage path, with tenant rewriting and validation.
     *
     * @param string $path Media path without "media/" and its disk (e.g. "cms/tenant/file.jpg")
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @return string|null Target storage path, or null if the entry should be skipped
     */
    protected function resolve( string $path, string $tenant, string $sourceTenant ): ?string
    {
        if( !$path || str_ends_with( $path, '/' ) ) {
            return null;
        }

        if( str_contains( $path, '..' ) || str_starts_with( $path, '/' ) ) {
            throw new \RuntimeException( sprintf( 'Unsafe media path detected: %s', $path ) );
        }

        $sourcePrefix = self::prefix( $sourceTenant );
        $targetPrefix = self::prefix( $tenant );

        $targetPath = str_starts_with( $path, $sourcePrefix )
            ? $targetPrefix . substr( $path, strlen( $sourcePrefix ) )
            : $path;

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
                $trackingFile = $this->tempdir() . '/cms-restore-' . ( $tenant !== '' ? $tenant : 'default' )
                    . '-' . bin2hex( random_bytes( 8 ) ) . '.log';
                $files = $this->mediaFiles( $zip, $tenant, $sourceTenant );
                $db = DB::connection( config( 'cms.db', 'sqlite' ) );
                $columns = [];
                $keepMedia = false;
                $removeTracking = false;

                if( !$this->option( 'media-only' ) )
                {
                    $columns = $this->classify( $db, $this->discover( $zip, $db ) );
                    $this->guardIds( $zip, $db, $columns, $tenant, (string) $sourceTenant );
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

                    $this->restoreDatabase( $zip, $db, $columns, $tenant, $sourceTenant, $merge, $files, $after );
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
     * @param Connection $db Database connection
     * @param array<string, list<string>> $columns Table name => column names
     * @param string $tenant Target tenant ID
     * @param string $sourceTenant Source tenant ID from backup
     * @param bool $merge Whether to merge (upsert) instead of replacing
     * @param array<string, array{disk: string, paths: list<string>}> $files Archive File catalog
     * @param \Closure|null $after Work to complete before the database transaction commits
     */
    protected function restoreDatabase( \ZipArchive $zip, Connection $db, array $columns, string $tenant,
        string $sourceTenant, bool $merge, array $files, ?\Closure $after = null ): void
    {
        $this->info( 'Restoring database...' );

        // Sort referenced tables first, then entity tables (with id) before pivot tables
        uksort( $columns, function( string $a, string $b ) use ( $columns ) {
            $aParent = $a === 'cms_pages';
            $bParent = $b === 'cms_pages';
            $aHasId = in_array( 'id', $columns[$a] );
            $bHasId = in_array( 'id', $columns[$b] );

            return ( $bParent <=> $aParent ) ?: ( $aHasId === $bHasId ? ( strlen( $b ) <=> strlen( $a ) ?: strcmp( $a, $b ) ) : ( $bHasId <=> $aHasId ) );
        } );

        $db->transaction( function() use ( $zip, $db, $tenant, $sourceTenant, $merge, $columns, $files, $after ) {

            // delete existing tenant data, pivot rows are removed by CASCADE
            foreach( $columns as $table => $cols )
            {
                if( !$merge && in_array( 'tenant_id', $cols ) ) {
                    $db->table( $table )->where( 'tenant_id', $tenant )->delete();
                }
            }

            foreach( $columns as $table => $cols )
            {
                $count = $this->import( $zip, $db, $table, $cols, $table . '.ndjson', $tenant, $sourceTenant, $merge, $files );
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

        $count = 0;
        $catalog = [];

        foreach( $files as $file ) {
            $catalog += array_fill_keys( $file['paths'], $file['disk'] );
        }

        for( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $stat = $zip->statIndex( $i );

            if( !$stat || !preg_match( '#^media/(public|private)/(.*)$#', $stat['name'], $matches ) ) {
                continue;
            }

            $logical = $matches[1];

            if( !( $targetPath = $this->resolve( $matches[2], $tenant, $sourceTenant ) ) ) {
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

            if( !( $stream = $zip->getStream( $stat['name'] ) ) ) {
                throw new \RuntimeException( sprintf( 'Failed to read media entry "%s"', $stat['name'] ) );
            }

            try
            {
                $this->trackMedia( $trackingFile, $logical, $targetPath );

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
            if( $table === 'cms_files' )
            {
                $row['path'] = self::move( $row['path'] ?? null, $sourceTenant, $tenant );
                $previews = json_decode( (string) ( $row['previews'] ?? '' ), true );

                if( is_array( $previews ) ) {
                    $row['previews'] = json_encode( array_map( fn( $path ) => self::move( $path, $sourceTenant, $tenant ), $previews ) );
                }
            }
            elseif( $table === 'cms_versions' && ( $row['versionable_type'] ?? null ) === File::class )
            {
                $data = json_decode( (string) ( $row['data'] ?? '' ), true );

                if( is_array( $data ) )
                {
                    $data['path'] = self::move( $data['path'] ?? null, $sourceTenant, $tenant );

                    if( is_array( $data['previews'] ?? null ) ) {
                        $data['previews'] = array_map( fn( $path ) => self::move( $path, $sourceTenant, $tenant ), $data['previews'] );
                    }

                    $row['data'] = json_encode( $data );
                }
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
     * Removes a completed or successfully rolled-back media journal.
     */
    protected function removeTracking( string $trackingFile ): void
    {
        ( new Filesystem() )->deleteDirectory( $trackingFile . '.d' );
        @unlink( $trackingFile );
    }


    /**
     * Yields the decoded rows of an NDJSON entry and closes its stream afterwards.
     *
     * @param \ZipArchive $zip ZIP archive
     * @param string $entry NDJSON entry name in ZIP
     * @return \Generator<int, array<string, mixed>> Decoded rows, invalid lines are skipped
     */
    protected function rows( \ZipArchive $zip, string $entry ): \Generator
    {
        if( !( $stream = $zip->getStream( $entry ) ) ) {
            return;
        }

        try
        {
            while( ( $line = fgets( $stream, self::MAX_LINE_LENGTH ) ) !== false )
            {
                if( is_array( $row = json_decode( trim( $line ), true ) ) ) {
                    yield $row;
                }
            }
        }
        finally
        {
            fclose( $stream );
        }
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
     * Journals the current state of one media path before it is changed.
     */
    protected function trackMedia( string $trackingFile, string $logical, string $path ): void
    {
        $dir = $trackingFile . '.d';

        if( !is_dir( $dir ) )
        {
            if( !touch( $trackingFile ) ) {
                throw new \RuntimeException( 'Failed to create tracking file: ' . $trackingFile );
            }

            @chmod( $trackingFile, 0600 );

            if( !mkdir( $dir, 0700, true ) && !is_dir( $dir ) ) {
                throw new \RuntimeException( 'Failed to create media rollback directory' );
            }
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

        if( file_put_contents( $trackingFile, $entry, FILE_APPEND | LOCK_EX ) === false
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
            $other = self::other( $file['disk'] );
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
        $disk = $this->checkDisk( $row );

        foreach( self::filePaths( $row ) as $path ) {
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

        foreach( self::versionPaths( $row ) as $path ) {
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
            hash_update_stream( $ctx, $stream );
            fclose( $stream );

            if( hash_final( $ctx ) !== $expectedHash )
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
        $this->table( ['Table', 'Records'], array_map( null, array_keys( $counts ), $counts ) );

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
