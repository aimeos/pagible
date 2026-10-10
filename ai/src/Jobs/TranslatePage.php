<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Prisma\Exceptions\OverloadedException;
use Aimeos\Prisma\Exceptions\RateLimitException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;


/**
 * Translates the source variant of a page into one language variant.
 *
 * Rate limit, overload and connection errors are retried with growing delays,
 * other errors fail at once. The provider calls are throttled per tenant.
 */
class TranslatePage implements ShouldBeUnique, ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;


    /** @var int Number of jobs added to the batch at once to stay below the placeholder limits */
    public const CHUNK = 500;


    /** @var int Hours a translation is retried before it fails */
    public const HOURS = 6;


    /** @var array<int, int> Delays in seconds before retrying after a provider error */
    public array $backoff = [10, 60, 300];

    /** @var int Number of provider errors after which the job fails */
    public int $maxExceptions = 4;

    /** @var string|null Authentication guard of the user who started the translation */
    public ?string $guard = null;

    /** @var string|null Class of the user who started the translation */
    public ?string $model = null;

    /** @var int Seconds until the translation of the page and language can be queued again if the job got lost */
    public int $uniqueFor = 3600 * self::HOURS;


    /**
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param string $tenant Tenant ID
     * @param int|string|null $userId ID of the user who started the translation
     */
    public function __construct( public string $id, public string $lang, public string $tenant, public int|string|null $userId )
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
        $this->guard = Auth::getDefaultDriver();

        if( ( $user = Auth::user() ) && $userId !== null && (string) $user->getAuthIdentifier() === (string) $userId ) {
            $this->model = get_class( $user );
        }
    }


    /**
     * Queues the translations of the pages which are needed as new batch.
     *
     * Only missing and stale variants are translated. Source variants, trashed pages and
     * trashed variants are skipped, as well as translations which are already queued. At most as
     * many translations are queued as the rate limit allows to run within the retry hours.
     *
     * @param iterable<string> $ids Page UUIDs in the order to translate
     * @param array<int, string> $langs Language codes of the variants, duplicates are ignored
     * @param int|string|null $userId ID of the user who starts the translation
     * @param bool $add TRUE to create missing variants, FALSE to update existing ones only
     * @return array{id: string, total: int} Batch ID and number of queued translations
     * @throws Exception If a language isn't configured or too many translations are needed
     */
    public static function dispatchPending( iterable $ids, array $langs, int|string|null $userId, bool $add = true ) : array
    {
        return self::enqueue( self::pending( iterator_to_array( $ids, false ), self::languages( $langs ), $add ), $userId );
    }


    /**
     * Returns the unique and valid language codes.
     *
     * Languages of existing variants can be used even if they aren't configured (anymore)
     * but only to update these variants. Any valid language can be used if no languages
     * are configured.
     *
     * @param array<int, string> $langs Language codes
     * @return array<int, string> Unique language codes
     * @throws Exception If a language isn't configured
     */
    protected static function languages( array $langs ) : array
    {
        $langs = array_values( array_unique( $langs ) );
        $locales = array_map( 'strval', (array) config( 'cms.locales', [] ) );

        if( ( $invalid = array_diff( $langs, $locales ) )
            && ( $invalid = array_diff( $invalid, PageVariant::whereIn( 'lang', $invalid )->distinct()->pluck( 'lang' )->all() ) )
            && ( $locales || array_filter( $invalid, fn( $lang ) => !Utils::isValidLang( $lang ) ) )
        ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', implode( '", "', $invalid ) ) );
        }

        return $langs;
    }


    /**
     * Returns the pages and languages whose variants are missing or stale.
     *
     * @param array<int, string> $ids Page UUIDs in the order to translate
     * @param array<int, string> $langs Language codes of the variants
     * @param bool $add TRUE to include missing variants
     * @return array<int, array{0: string, 1: string}> List of page ID and language pairs in the order of the IDs
     */
    protected static function pending( array $ids, array $langs, bool $add ) : array
    {
        $db = PageVariant::query()->getConnection();
        $tenant = Tenancy::value();
        $pairs = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), 500 ) as $chunk )
        {
            $sources = $db->table( 'cms_pages' )
                ->where( 'tenant_id', $tenant )
                ->whereNull( 'deleted_at' )
                ->whereIn( 'id', $chunk )
                ->pluck( 'source', 'id' );

            $variants = [];
            $rows = $db->table( 'cms_page_variants' )
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'page_id', $chunk )
                ->whereIn( 'lang', $langs )
                ->get( ['page_id', 'lang', 'stale', 'deleted_at'] );

            foreach( $rows as $row ) {
                $variants[$row->page_id][$row->lang] = $row;
            }

            // missing variants are only created for configured languages, others are only updated
            $adds = array_fill_keys( array_filter( $langs, fn( $lang ) => $add && Utils::isLocale( $lang ) ), true );

            foreach( $chunk as $id )
            {
                if( !isset( $sources[$id] ) ) {
                    continue;
                }

                foreach( $langs as $lang )
                {
                    $variant = $variants[$id][$lang] ?? null;

                    if( $lang !== $sources[$id] && ( $variant ? !$variant->deleted_at && $variant->stale : isset( $adds[$lang] ) ) ) {
                        $pairs[] = [(string) $id, $lang];
                    }
                }
            }
        }

        // later jobs would wait longer than they are retried
        if( count( $pairs ) > ( $max = self::max() * 60 * self::HOURS ) ) {
            throw new Exception( sprintf( 'No more than %d translations may be queued at once.', $max ) );
        }

        return $pairs;
    }


    /**
     * Queues the translations as new batch.
     *
     * Translations of a page and language which are already queued are skipped and not
     * counted, so the progress of the batch can complete. Batches don't acquire the unique
     * locks of the jobs themselves but they are released when the jobs are finished.
     *
     * @param array<int, array{0: string, 1: string}> $pairs List of page ID and language pairs
     * @param int|string|null $userId ID of the user who starts the translation
     * @return array{id: string, total: int} Batch ID and number of queued translations
     */
    protected static function enqueue( array $pairs, int|string|null $userId ) : array
    {
        $lock = new UniqueLock( app( \Illuminate\Contracts\Cache\Repository::class ) );
        $jobs = [];

        foreach( $pairs as [$id, $lang] )
        {
            $job = new self( $id, $lang, Tenancy::value(), $userId );
            // spread the jobs over the minutes the rate limit allows them to run
            $delay = intdiv( count( $jobs ), self::max() ) * 60;
            // locks of lost jobs expire after the job would have been retried
            $job->uniqueFor = $delay + 3600 * self::HOURS + 600;

            if( $lock->acquire( $job ) ) {
                $jobs[] = $job->delay( $delay );
            }
        }

        $chunks = array_chunk( $jobs, self::CHUNK );
        $connection = config( 'cms.queue.connection' );
        $queue = config( 'cms.queue.name' );
        $added = 0;

        try
        {
            $batch = Bus::batch( $chunks[0] ?? [] )->name( self::batchName() )->allowFailures()
                ->when( $connection, fn( $batch ) => $batch->onConnection( $connection ) )
                ->when( $queue, fn( $batch ) => $batch->onQueue( $queue ) )
                ->dispatch();

            $added = count( $chunks[0] ?? [] );

            foreach( array_slice( $chunks, 1 ) as $chunk )
            {
                $batch->add( $chunk );
                $added += count( $chunk );
            }
        }
        catch( \Throwable $e )
        {
            array_map( fn( $job ) => $lock->release( $job ), array_slice( $jobs, $added ) );
            throw $e;
        }

        return ['id' => $batch->id, 'total' => count( $jobs )];
    }


    /**
     * Returns the name of the translation batches of the current tenant.
     *
     * The tenant is hashed because its ID can be longer than the column of the batch name.
     *
     * @return string Batch name
     */
    protected static function batchName() : string
    {
        return 'cms-translate:' . sha1( (string) Tenancy::value() );
    }


    /**
     * Returns the progress of a batch of translations.
     *
     * @param string $batch Batch ID
     * @return array{total: int, done: int, failed: int}|null Progress or NULL if the batch is unknown
     */
    public static function progress( string $batch ) : ?array
    {
        // batches are shared by all tenants and the application
        if( !( $batch = Bus::findBatch( $batch ) ) || $batch->name !== self::batchName() ) {
            return null;
        }

        return [
            'total' => $batch->totalJobs,
            'done' => $batch->totalJobs - $batch->pendingJobs,
            'failed' => $batch->failedJobs,
        ];
    }


    /**
     * Translates the page variant as the user who started the translation.
     *
     * Rate limit, overload and connection errors are thrown so the queue retries the job
     * after the delays of the back-off, other errors fail at once.
     */
    public function handle() : void
    {
        try
        {
            // cheap check before loading the page, doesn't count as attempt
            if( ( $wait = self::wait( $this->tenant ) ) > 0 ) {
                throw new Throttled( $wait );
            }

            Tenancy::run( $this->tenant, function() {

                // pages moved to the trash or deleted after queuing aren't translated anymore
                if( !Page::whereKey( $this->id )->exists() ) {
                    return;
                }

                if( !( $user = $this->user() ) || !Permission::can( 'page:save', $user ) || ( !Permission::can( 'page:add', $user )
                    && !PageVariant::withTrashed()->where( 'page_id', $this->id )->where( 'lang', $this->lang )->exists() )
                ) {
                    throw new Exception( 'Insufficient permissions' );
                }

                // queue workers are long running, so later jobs mustn't run as this user
                $previous = Auth::hasUser() ? Auth::user() : null;
                Auth::setUser( $user );

                try {
                    Ai::retryable( fn() => Resource::translatePage( $this->id, $this->lang, $user, Ai::translator( $user ) ), $this->tenant );
                } finally {
                    $previous ? Auth::setUser( $previous ) : Auth::forgetUser();
                }
            } );
        }
        catch( Throttled $e )
        {
            if( $this->sync() ) {
                throw new Exception( 'Too many translations, please try again later' );
            }

            // spread retries over the time the waiting jobs need, so they don't all run again in the next minute
            $waiting = max( 1, $this->batch()->pendingJobs ?? 1 );
            $this->release( $e->delay + random_int( 0, (int) ceil( $waiting / self::max() ) * 60 ) );
        }
        catch( RateLimitException|OverloadedException|ConnectException $e )
        {
            throw $e;
        }
        catch( \Throwable $e )
        {
            if( $this->sync() ) {
                throw $e;
            }

            $this->fail( $e );
        }
    }


    /**
     * Logs the failed translation.
     *
     * @param \Throwable $e Error which occurred
     */
    public function failed( \Throwable $e ) : void
    {
        Log::error( 'Page translation failed', [
            'page' => $this->id,
            'lang' => $this->lang,
            'tenant' => $this->tenant,
            'error' => $e->getMessage(),
        ] );
    }


    /**
     * Allows retrying until the job is released too often because of the rate limit.
     *
     * The hours start when the job is due, so delayed jobs have the same time for retries.
     */
    public function retryUntil() : \DateTimeInterface
    {
        return now()->addSeconds( is_int( $this->delay ) ? $this->delay : 0 )->addHours( self::HOURS );
    }


    /**
     * Returns the key of the rate limiter for the given tenant.
     *
     * @param string $tenant Tenant ID
     */
    protected static function limiter( string $tenant ) : string
    {
        // hashed so tenant IDs containing ":" can't collide with the ":until" keys of other tenants
        return 'cms-translate:' . sha1( $tenant );
    }


    /**
     * Returns the maximum number of provider calls per minute.
     */
    protected static function max() : int
    {
        return max( 1, (int) config( 'cms.ai.ratelimit', 60 ) );
    }


    /**
     * Returns the ID which makes the job unique per page and language.
     */
    public function uniqueId() : string
    {
        return hash( 'sha256', $this->tenant . ':' . $this->id . ':' . $this->lang );
    }


    /**
     * Tests if the job runs inline instead of from a queue.
     *
     * @return bool TRUE if the job runs synchronously
     */
    protected function sync() : bool
    {
        return !$this->job || $this->job instanceof SyncJob;
    }


    /**
     * Reserves AI translation provider calls of the tenant.
     *
     * The calls are counted atomically first and given back if the limit is exceeded, so
     * concurrent translations can't exceed the limit together. If more calls are needed than allowed
     * per minute, they are only reserved if no other calls have been made in the current minute and
     * the calls exceeding the limit are taken from the following minutes.
     *
     * @param string $tenant Tenant ID
     * @param int $calls Number of provider calls to make
     * @throws Throttled If the rate limit would be exceeded
     */
    public static function reserve( string $tenant, int $calls ) : void
    {
        $key = self::limiter( $tenant );

        if( ( $wait = (int) Cache::get( $key . ':until', 0 ) - now()->getTimestamp() ) > 0 ) {
            throw new Throttled( $wait );
        }

        $hits = RateLimiter::increment( $key, 60, $calls );

        if( $hits > self::max() && $hits > $calls )
        {
            RateLimiter::decrement( $key, 60, $calls );
            throw new Throttled( max( 1, RateLimiter::availableIn( $key ) ) );
        }

        // no calls are made in the following minutes until the calls exceeding the limit are used up
        if( $hits > self::max() )
        {
            $wait = RateLimiter::availableIn( $key ) + ( (int) ceil( $hits / self::max() ) - 1 ) * 60;
            Cache::put( $key . ':until', now()->getTimestamp() + $wait, $wait );
        }
    }


    /**
     * Returns the seconds to wait until provider calls of the tenant are allowed again.
     *
     * @param string $tenant Tenant ID
     * @return int Seconds to wait or 0 if calls are allowed
     */
    protected static function wait( string $tenant ) : int
    {
        $key = self::limiter( $tenant );

        if( ( $wait = (int) Cache::get( $key . ':until', 0 ) - now()->getTimestamp() ) > 0 ) {
            return $wait;
        }

        return RateLimiter::remaining( $key, self::max() ) < 1 ? max( 1, RateLimiter::availableIn( $key ) ) : 0;
    }


    /**
     * Returns the user who started the translation.
     *
     * @return Authenticatable|null User or NULL if not available anymore
     */
    protected function user() : ?Authenticatable
    {
        if( $this->userId === null ) {
            return null;
        }

        // the user is loaded by the provider of the guard the user was authenticated with
        $guard = $this->guard ?? config( 'auth.defaults.guard' );
        $provider = Auth::createUserProvider( config( "auth.guards.$guard.provider" ) );

        $user = $provider?->retrieveById( $this->userId );

        // guards without own provider (e.g. Sanctum) may load another model with the same ID
        return $user && ( $this->model === null || get_class( $user ) === $this->model ) ? $user : null;
    }
}

