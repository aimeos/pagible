<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\FilePreviews;
use Aimeos\Cms\ContentAction;
use Aimeos\Cms\Files;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Aimeos\Cms\DB;


/**
 * Creates previews for missing configured sizes and removes previews of sizes no longer configured.
 *
 * The new previews are stored in a new version of the file, which is published if the latest
 * version was. Removed previews are never deleted directly. They are still referenced by the
 * previous versions and deleted when these versions are pruned.
 *
 * @phpstan-import-type PreviewDiff from FilePreviews
 * @phpstan-type PreviewSource array{file: File|null, version: Version|null, item: array{file: File, previews: array<int, string>}|null}
 */
class Previews extends Command
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
    private const INVALIDATE = 500;

    /**
     * Files whose versions are pruned at once, which loads all their kept versions
     */
    private const PRUNE = 100;

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
    protected $description = 'Updates image previews to the configured sizes';

    /**
     * If a line of progress dots hasn't been terminated yet
     */
    private bool $dots = false;


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
     * Updates the previews of the file and adds the result to the statistics.
     *
     * Errors of single files are reported and don't stop the processing of the other files
     * but a locked storage stops the processing of the tenant.
     *
     * @param string $id File ID
     * @param array{updated: int, skipped: list<string>, failed: list<string>} $stats Statistics updated in place
     * @return bool TRUE if the previews of the file have been updated, FALSE if not
     * @throws LockTimeoutException If the storage is locked for too long, e.g. by a running backup
     */
    protected function attempt( string $id, array &$stats ) : bool
    {
        try {
            $result = $this->sync( $id );
        } catch( LockTimeoutException $e ) {
            throw $e;
        } catch( \Throwable $t ) {
            $this->flush();
            $this->error( sprintf( 'File "%s": %s', $id, $t->getMessage() ) );
            $stats['failed'][] = $id;
            return false;
        }

        $stats = $this->report( $stats, $id, $result );
        return $result['updated'];
    }


    /**
     * Updates the previews of a batch of files and notifies about the updated ones.
     *
     * Updated files are notified even if a later file of the batch throws an exception.
     *
     * @param array<string> $ids File IDs
     * @param array{updated: int, skipped: list<string>, failed: list<string>} $stats Statistics updated in place
     * @param array<string> $live IDs of the files whose previews are live so far
     * @param array<string> $changed IDs of the files updated so far
     */
    protected function batch( array $ids, array &$stats, array &$live, array &$changed ) : void
    {
        $updated = [];

        try
        {
            foreach( $ids as $id )
            {
                if( $this->attempt( $id, $stats ) ) {
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
    }


    /**
     * Tests if the file or its latest version has changed since the previews were generated.
     *
     * @param PreviewSource $source File and version the previews were generated from
     * @param PreviewSource $current Current file and version
     * @return bool TRUE if the file, its storage location, previews or latest version differ
     */
    protected static function changed( array $source, array $current ) : bool
    {
        $file = $source['file'];
        $now = $current['file'];

        return !$file || !$now || $now->getAttribute( 'disk' ) !== $file->getAttribute( 'disk' )
            || $now->path !== $file->path || $now->latest_id !== $file->latest_id
            || self::previews( $now->previews ) !== self::previews( $file->previews )
            || $current['version']?->data != $source['version']?->data
            || $current['version']?->published !== $source['version']?->published;
    }


    /**
     * Terminates the line of progress dots before other output is written.
     */
    protected function flush() : void
    {
        if( $this->dots )
        {
            $this->newLine();
            $this->dots = false;
        }
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
        $db = DB::connection();
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
        File::announceMany( $files, ContentAction::Saved, self::EDITOR, [], bulk: true );

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
     * @return PreviewDiff|null
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
            Invalidation::refs( [], $chunk );
        }

        try
        {
            // locked per chunk so other media operations of the tenant can run in between
            foreach( array_chunk( $ids, self::PRUNE ) as $chunk ) {
                File::locked( fn() => File::pruneVersions( Tenancy::value(), $chunk ) );
            }
        }
        catch( LockTimeoutException )
        {
            $this->flush();
            $this->warn( sprintf( 'Tenant "%s": Stopped pruning old versions because the storage is locked', Tenancy::value() ) );
        }
    }


    /**
     * Reports the result of updating the previews of the file and adds it to the statistics.
     *
     * Updated files are reported as dots in verbose mode (-v) and by their IDs in very verbose mode (-vv).
     *
     * @param array{updated: int, skipped: list<string>, failed: list<string>} $stats Statistics
     * @param string $id File ID
     * @param array{updated: bool, skipped: bool} $result Result of updating the previews
     * @return array{updated: int, skipped: list<string>, failed: list<string>} Updated statistics
     */
    protected function report( array $stats, string $id, array $result ) : array
    {
        if( $result['skipped'] )
        {
            $this->flush();
            $this->warn( sprintf( 'File "%s": Skipped because it has been changed in the meantime', $id ) );
            $stats['skipped'][] = $id;
        }

        if( $result['updated'] && $this->output->isVeryVerbose() ) {
            $this->line( sprintf( 'File "%s": Updated', $id ) );
        } elseif( $result['updated'] && $this->output->isVerbose() ) {
            $this->output->write( '.' );
            $this->dots = true;
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
     * @return PreviewSource
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
     * was. Files without versions are updated directly. Must be called while the storage
     * lock is held.
     *
     * @param string $id File ID
     * @param PreviewSource $source
     * @param array<int, string> $map New previews as width/path pairs
     * @return bool TRUE if the previews have been stored, FALSE if the file has been changed
     */
    protected function store( string $id, array $source, array $map ) : bool
    {
        $file = $source['file'];
        $version = $source['version'];

        if( !$file || self::changed( $source, $this->source( $id ) ) ) {
            return false;
        }

        DB::transaction( function() use ( $file, $map, $version ) {
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

        $disk = Files::disk( (string) $item['file']->getAttribute( 'disk' ) );
        $created = [];

        try
        {
            $map = $item['file']->syncPreviews( $item['previews'], $plan );
            $created = array_values( array_diff( (array) $map, $item['previews'] ) );
            // FALSE if the file has been changed meanwhile
            $updated = $map !== null && File::locked( fn() => $this->store( $id, $source, $map ) );
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
                $this->batch( $ids, $stats, $live, $changed );
            } );
        }
        catch( LockTimeoutException )
        {
            $this->flush();
            // otherwise, each remaining file would wait for the lock until the backup has finished
            $this->error( sprintf( 'Tenant "%s": Stopped because the storage is locked, e.g. by a running backup', $tenant ) );
            $locked = true;
        }
        finally
        {
            $this->refresh( $live, $changed );
        }

        $this->flush();

        $this->info( sprintf( 'Tenant "%s": %d file(s) updated, %d skipped, %d failed', $tenant,
            $stats['updated'], count( $stats['skipped'] ), count( $stats['failed'] ) ) );

        $this->monitor( $tenant, $stats, $locked );

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

        $db = DB::connection();

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
        // progress bars flood the logs of non-interactive runs with lines and would overwrite the verbose output
        $bar = $this->output->isDecorated() && !$this->output->isVerbose() ? $this->output->createProgressBar( $query->count() ) : null;
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


    /**
     * Logs skipped and failed files because the exit code only reports failures.
     *
     * @param string $tenant Tenant ID
     * @param array{updated: int, skipped: list<string>, failed: list<string>} $stats Statistics
     * @param bool $locked TRUE if the processing stopped because the storage is locked
     */
    protected function monitor( string $tenant, array $stats, bool $locked ) : void
    {
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
    }
}
