<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


/**
 * Creates previews for missing configured sizes and removes previews of sizes no longer configured.
 *
 * The new previews are stored in a new version of the file, which is published if the latest
 * version was. Removed previews are never deleted directly. They are still referenced by the
 * previous versions and deleted when these versions are pruned.
 */
class Previews extends Command implements Isolatable
{
    /**
     * Editor name of the created versions
     */
    public const EDITOR = 'cms:previews';

    /**
     * Files processed and notified at once
     */
    public const CHUNK = 10;

    /**
     * Files whose pages are invalidated at once
     */
    public const INVALIDATE = 500;

    /**
     * Files whose versions are pruned at once, which loads all their kept versions
     */
    public const PRUNE = 100;

    /**
     * Command name
     */
    protected $signature = 'cms:previews
        {--tenant= : Only update the files of this tenant, use --tenant= for the default (empty) tenant}
        {--id=* : Only update the files with these IDs (can be used multiple times)}
        {--force : Replace all previews of files which are images, e.g. after changing the image format or quality}';

    /**
     * Command description
     */
    protected $description = 'Creates missing preview images for the configured sizes and removes previews of sizes no longer configured';

    /**
     * Don't run the command if another instance is already running
     */
    protected $isolated = true;


    /**
     * Execute command
     */
    public function handle(): int
    {
        if( ( $tenants = $this->tenants() ) === null ) {
            return self::FAILURE;
        }

        $failed = false;

        foreach( $tenants as $tenant ) {
            $failed = !Tenancy::run( $tenant, fn() => $this->tenant( $tenant ) ) || $failed;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }


    /**
     * Returns when the isolation lock expires if the command has been killed.
     *
     * Runs of large tenants can take several hours and a second instance would
     * generate the same previews again.
     *
     * @return \DateInterval Lifetime of the isolation lock
     */
    public function isolationLockExpiresAt() : \DateInterval
    {
        return new \DateInterval( 'P1D' );
    }


    /**
     * Returns the ID of the isolation lock, which is per tenant for tenants managed by Stancl.
     *
     * @return string Lock ID
     */
    public function isolatableId() : string
    {
        return Tenancy::managed() ? 'tenant-' . Tenancy::value() : 'all';
    }


    /**
     * Stores the new previews under the storage and file locks.
     *
     * Files locked by other operations for too long are skipped like changed files.
     *
     * @param string $id File ID
     * @param array{file: File|null, version: Version|null, item: array{file: File, previews: array<int, string>}|null} $source
     * @param array<int, string> $map New previews as width/path pairs
     * @return bool TRUE if the previews have been stored, FALSE if the file has been changed or is locked
     * @throws LockTimeoutException If the storage is locked for too long, e.g. by a running backup
     */
    protected function commit( string $id, array $source, array $map ) : bool
    {
        return Utils::storageLock( Tenancy::value(), function() use ( $id, $map, $source ) {
            try {
                return Utils::fileLock( Tenancy::value(), $id, fn() => $this->store( $id, $source, $map ) );
            } catch( LockTimeoutException ) {
                return false;
            }
        } );
    }


    /**
     * Returns the query for the files of the current tenant.
     *
     * Only the values required for planning are fetched from the data of the latest versions,
     * which can be large due to descriptions and transcriptions.
     *
     * @return Builder<File> Query for the files including their latest versions
     */
    protected function files() : Builder
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        // JSON_VALUE() used by Laravel for SQL Server returns NULL for objects
        $previews = $db->getDriverName() === 'sqlsrv'
            ? $db->raw( "JSON_QUERY([data], '$.previews') AS [previews]" )
            : 'data->previews as previews';

        $query = File::select( 'id', 'mime', 'path', 'previews', 'latest_id' )->with( [
            'latest' => fn( $query ) => $query->select( 'id', 'data->mime as mime', 'data->path as path', $previews )
        ] );

        if( $ids = (array) $this->option( 'id' ) ) {
            $query->whereIn( 'id', $ids );
        }

        return $query;
    }


    /**
     * Announces the changed files.
     *
     * @param array<string> $ids File IDs
     * @return array<string> IDs of the files whose previews are live, i.e. whose new version is published or which have no versions
     */
    protected function notify( array $ids ) : array
    {
        $files = File::withTrashed()->select( 'id', 'latest_id' )->with( 'latest:id,published' )->whereIn( 'id', $ids )->get();
        File::announceMany( $files, 'saved', self::EDITOR, [], true );

        // the live files and therefore the pages don't change if only a draft version has been added
        return $files->filter( fn( File $file ) => $file->latest->published ?? true )->modelKeys();
    }


    /**
     * Returns the file whose previews must be synchronized and its previews.
     *
     * This is the latest version or the file itself if it has no versions.
     *
     * @param File $file File
     * @param object|null $data Data of the latest version of the file
     * @return array{file: File, previews: array<int, string>} File and its previews as width/path pairs
     */
    protected static function item( File $file, ?object $data ) : array
    {
        if( !$data ) {
            return ['file' => $file, 'previews' => self::previews( $file->previews )];
        }

        $draft = ( new File() )->forceFill( [
            'id' => $file->id,
            'disk' => $file->getAttribute( 'disk' ),
            'mime' => (string) ( $data->mime ?? '' ),
            'name' => (string) ( $data->name ?? '' ),
            'path' => $data->path ?? null,
        ] );

        return ['file' => $draft, 'previews' => self::previews( $data->previews ?? [] )];
    }


    /**
     * Compares the previews of the file with the configured sizes without generating images.
     *
     * @param array{file: File, previews: array<int, string>} $item File and its previews
     * @return array{keep: array<int, string>, missing: array<string, array<string, mixed>>}|null
     *  Previews to keep and missing sizes or NULL if nothing must be changed
     */
    protected function plan( array $item ) : ?array
    {
        $plan = $item['file']->diffPreviews( $item['previews'], (bool) $this->option( 'force' ) );

        return $plan['missing'] || array_diff( $item['previews'], $plan['keep'] ) ? $plan : null;
    }


    /**
     * Returns the previews as width/path pairs sorted by width.
     *
     * @param mixed $previews Previews object or array
     * @return array<int, string> Sorted width/path pairs
     */
    protected static function previews( mixed $previews ) : array
    {
        $list = array_map( 'strval', (array) $previews );
        ksort( $list );

        return $list;
    }


    /**
     * Invalidates the cached pages using the live files and prunes the old versions of the changed files.
     *
     * Done once per tenant so pages using files of different batches aren't invalidated and
     * rendered again repeatedly. The pages must be invalidated first because pruning can
     * delete the previews still referenced by the cached pages. Pruning is skipped if the
     * storage is locked, e.g. by a running backup, and done by the next save of the files.
     *
     * @param array<string> $live IDs of the files whose previews are live
     * @param array<string> $ids IDs of all changed files
     */
    protected function refresh( array $live, array $ids ) : void
    {
        foreach( array_chunk( $live, self::INVALIDATE ) as $chunk ) {
            Resource::invalidateRefs( [], $chunk );
        }

        try
        {
            // locked per chunk so other media operations of the tenant can run in between
            foreach( array_chunk( $ids, self::PRUNE ) as $chunk ) {
                Utils::storageLock( Tenancy::value(), fn() => File::pruneVersions( Tenancy::value(), $chunk ) );
            }
        }
        catch( LockTimeoutException )
        {
            $this->warn( sprintf( 'Tenant "%s": Stopped pruning old versions because the storage is locked', Tenancy::value() ) );
        }
    }


    /**
     * Reports the result of updating the previews of the file and adds it to the statistics.
     *
     * @param array{updated: int, skipped: list<string>, failed: list<string>} $stats Statistics
     * @param string $id File ID
     * @param array{updated: bool, skipped: bool}|string $result Result or error message
     * @return array{updated: int, skipped: list<string>, failed: list<string>} Updated statistics
     */
    protected function report( array $stats, string $id, array|string $result ) : array
    {
        if( is_string( $result ) )
        {
            $this->error( sprintf( 'File "%s": %s', $id, $result ) );
            $stats['failed'][] = $id;
            return $stats;
        }

        if( $result['skipped'] )
        {
            $this->warn( sprintf( 'File "%s": Skipped because it has been changed in the meantime', $id ) );
            $stats['skipped'][] = $id;
        }

        $stats['updated'] += (int) $result['updated'];
        return $stats;
    }


    /**
     * Returns the file, its latest version and the source whose previews must be synchronized.
     *
     * The source is the latest version or the file itself if it has no versions.
     *
     * @param string $id File ID
     * @return array{file: File|null, version: Version|null, item: array{file: File, previews: array<int, string>}|null}
     */
    protected function source( string $id ) : array
    {
        $file = File::withTrashed()->select( 'id', 'disk', 'mime', 'name', 'path', 'previews', 'latest_id' )->find( $id );

        if( !$file ) {
            return ['file' => null, 'version' => null, 'item' => null];
        }

        $version = $file->latest_id
            ? Version::select( 'id', 'data', 'aux', 'lang', 'publish_at', 'published' )->find( $file->latest_id )
            : null;

        return ['file' => $file, 'version' => $version, 'item' => self::item( $file, $version?->data )];
    }


    /**
     * Stores the new previews if the file and its latest version haven't changed in the meantime.
     *
     * Creates a new version with the new previews, which is published if the latest version
     * was. Files without versions are updated directly. Must be called while the storage and
     * file locks are held.
     *
     * @param string $id File ID
     * @param array{file: File|null, version: Version|null, item: array{file: File, previews: array<int, string>}|null} $source
     * @param array<int, string> $map New previews as width/path pairs
     * @return bool TRUE if the previews have been stored, FALSE if the file has been changed
     */
    protected function store( string $id, array $source, array $map ) : bool
    {
        $current = $this->source( $id );
        $file = $source['file'];
        $version = $source['version'];

        if( !$file || !$current['file'] || $current['file']->getAttribute( 'disk' ) !== $file->getAttribute( 'disk' )
            || $current['file']->path !== $file->path || $current['file']->latest_id !== $file->latest_id
            || self::previews( $current['file']->previews ) !== self::previews( $file->previews )
            || $current['version']?->data != $version?->data || $current['version']?->published !== $version?->published
        ) {
            return false;
        }

        Utils::transaction( function() use ( $file, $map, $version ) {
            if( !$version ) {
                File::withTrashed()->whereKey( $file->id )->toBase()->update( ['previews' => json_encode( (object) $map )] );
                return;
            }

            $data = clone $version->data;
            $data->previews = (object) $map;

            $new = $file->versions()->forceCreate( [
                'lang' => $version->lang,
                'editor' => self::EDITOR,
                'data' => $data,
                'aux' => $version->aux,
                'publish_at' => $version->publish_at,
                'published' => $version->published,
            ] );

            $values = ['latest_id' => $new->id];

            // the file contains the values of the published version
            if( $version->published ) {
                $values['previews'] = json_encode( (object) $map );
            }

            File::withTrashed()->whereKey( $file->id )->toBase()->update( $values );
        } );

        return true;
    }


    /**
     * Updates the previews of the file in the current tenant without notifying about the change.
     *
     * The previews are generated from the latest version before the storage lock is
     * acquired so other media operations of the tenant aren't blocked by the image
     * processing. Nothing is changed if the file has been modified in the meantime.
     *
     * @param string $id File ID
     * @return array{updated: bool, skipped: bool} If the file has been updated or skipped
     */
    protected function sync( string $id ) : array
    {
        $source = $this->source( $id );

        if( !( $item = $source['item'] ) ) {
            return ['updated' => false, 'skipped' => false];
        }

        // the file may have been changed since it has been planned by walk()
        if( !( $plan = $this->plan( $item ) ) ) {
            return ['updated' => false, 'skipped' => false];
        }

        $disk = Storage::disk( File::diskName( (string) $item['file']->getAttribute( 'disk' ) ) );
        $created = [];

        try
        {
            $map = $item['file']->syncPreviews( $item['previews'], $plan );
            $created = array_values( array_diff( (array) $map, $item['previews'] ) );
            $updated = $map !== null && $this->commit( $id, $source, $map );
        }
        catch( \Throwable $t )
        {
            $disk->delete( $created );
            throw $t;
        }

        if( !$updated ) {
            $disk->delete( $created );
        }

        return ['updated' => $updated, 'skipped' => $map !== null && !$updated];
    }


    /**
     * Updates the previews of the files of the current tenant.
     *
     * @param string $tenant Tenant ID
     * @return bool TRUE if all files have been processed successfully, FALSE if not
     */
    protected function tenant( string $tenant ) : bool
    {
        $stats = ['updated' => 0, 'skipped' => [], 'failed' => []];
        $changed = $live = [];
        $locked = false;

        try
        {
            $this->walk( function( array $ids ) use ( &$changed, &$live, &$stats ) {
                $updated = [];

                try
                {
                    foreach( $ids as $id )
                    {
                        // errors of single files don't stop the processing of the other files
                        try {
                            $result = $this->sync( $id );
                        } catch( LockTimeoutException $e ) {
                            throw $e;
                        } catch( \Throwable $t ) {
                            $result = $t->getMessage();
                        }

                        $stats = $this->report( $stats, $id, $result );

                        if( is_array( $result ) && $result['updated'] ) {
                            $updated[] = $id;
                        }
                    }
                }
                finally
                {
                    if( $updated ) {
                        $live = array_merge( $live, $this->notify( $updated ) );
                        $changed = array_merge( $changed, $updated );
                    }
                }
            } );
        }
        catch( LockTimeoutException )
        {
            // otherwise, each remaining file would wait for the lock until the backup has finished
            $this->error( sprintf( 'Tenant "%s": Stopped because the storage is locked, e.g. by a running backup', $tenant ) );
            $locked = true;
        }
        finally
        {
            $this->refresh( $live, $changed );
        }

        $this->info( sprintf( 'Tenant "%s": %d file(s) updated, %d skipped, %d failed', $tenant,
            $stats['updated'], count( $stats['skipped'] ), count( $stats['failed'] ) ) );

        // the exit code only reports failures, so monitoring must be able to find skipped files
        if( $stats['skipped'] || $stats['failed'] || $locked )
        {
            Watch::warn( 'cms.previews', [
                'action' => 'sync',
                'tenant_id' => $tenant,
                'skipped' => $stats['skipped'],
                'failed' => $stats['failed'],
                'locked' => $locked,
            ] );
        }

        // files changed by editors in the meantime are no error
        return !$stats['failed'] && !$locked;
    }


    /**
     * Returns the tenants whose files should be updated.
     *
     * @return array<string>|null List of tenant IDs or NULL if the tenant can't be processed
     */
    protected function tenants() : ?array
    {
        $tenant = $this->option( 'tenant' );

        // Stancl only allows operations in the tenant context it has initialized
        if( Tenancy::managed() )
        {
            if( $tenant !== null && $tenant !== Tenancy::value() )
            {
                $this->error( 'Run the command for other tenants using: php artisan tenants:run cms:previews' );
                return null;
            }

            return [Tenancy::value()];
        }

        if( $tenant !== null ) {
            return [Tenancy::check( (string) $tenant )];
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        return $db->table( 'cms_files' )->distinct()->orderBy( 'tenant_id' )->pluck( 'tenant_id' )->map( strval(...) )->all();
    }


    /**
     * Calls the callback for the files of the current tenant whose previews must be changed.
     *
     * @param \Closure(list<string>): void $callback Callback receiving the file IDs
     */
    protected function walk( \Closure $callback ) : void
    {
        $query = $this->files();
        // progress bars flood the logs of non-interactive runs with lines
        $bar = $this->output->isDecorated() ? $this->output->createProgressBar( $query->count() ) : null;
        $batch = [];

        foreach( $query->lazyById( 100 ) as $file )
        {
            $data = ( $latest = $file->latest ) ? (object) [
                'mime' => $latest->getAttribute( 'mime' ),
                'path' => $latest->getAttribute( 'path' ),
                'previews' => json_decode( (string) $latest->getAttribute( 'previews' ) ),
            ] : null;

            // planned from the already loaded data, so up-to-date files cost no further queries
            if( $this->plan( self::item( $file, $data ) ) ) {
                $batch[] = (string) $file->id;
            }

            $bar?->advance();

            if( count( $batch ) >= self::CHUNK )
            {
                $bar?->clear();
                $callback( $batch );
                $batch = [];
            }
        }

        if( $batch ) {
            $callback( $batch );
        }

        $bar?->clear();
    }
}
