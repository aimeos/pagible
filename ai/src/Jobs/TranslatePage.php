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


    /** @var array<int, int> Delays in seconds before retrying after a provider error */
    public array $backoff = [10, 60, 300];

    /** @var int Number of provider errors after which the job fails */
    public int $maxExceptions = 4;


    /**
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param string $tenant Tenant ID
     * @param int|string|null $userId ID of the user who started the translation
     */
    public function __construct( public string $id, public string $lang, public string $tenant, public int|string|null $userId )
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
    }


    /**
     * Queues one translation per page and language as new batch.
     *
     * Translations of a page and language which are already queued are skipped and
     * not counted, so the progress of the batch can complete.
     *
     * @param iterable<string> $ids Page UUIDs in the order to translate
     * @param array<int, string> $langs Language codes of the variants, duplicates are ignored
     * @param int|string|null $userId ID of the user who starts the translation
     * @return array{id: string, total: int} Batch ID and number of queued translations
     * @throws Exception If a language isn't configured or there are too many translations
     */
    public static function dispatchBatch( iterable $ids, array $langs, int|string|null $userId ) : array
    {
        $ids = iterator_to_array( $ids, false );
        $langs = array_values( array_unique( $langs ) );
        $locales = array_map( 'strval', (array) config( 'cms.locales', [] ) );

        // languages of existing variants can be updated even if they aren't configured (anymore)
        // and any valid language can be used if no languages are configured
        if( ( $invalid = array_diff( $langs, $locales ) )
            && ( $invalid = array_diff( $invalid, PageVariant::whereIn( 'lang', $invalid )->distinct()->pluck( 'lang' )->all() ) )
            && ( $locales || array_filter( $invalid, fn( $lang ) => !Utils::isValidLang( $lang ) ) )
        ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', implode( '", "', $invalid ) ) );
        }

        Page::checkBulk( count( $ids ) * count( $langs ) );

        $lock = new UniqueLock( app( \Illuminate\Contracts\Cache\Repository::class ) );
        $jobs = [];

        foreach( $ids as $id )
        {
            foreach( $langs as $lang )
            {
                $job = new self( (string) $id, $lang, Tenancy::value(), $userId );

                // spread the jobs over the minutes the rate limit allows them to run
                if( $lock->acquire( $job ) ) {
                    $jobs[] = $job->delay( intdiv( count( $jobs ), self::max() ) * 60 );
                }
            }
        }

        $chunks = array_chunk( $jobs, self::CHUNK );
        $connection = config( 'cms.queue.connection' );
        $queue = config( 'cms.queue.name' );
        $added = 0;

        try
        {
            $batch = Bus::batch( $chunks[0] ?? [] )->name( 'cms-translate' )->allowFailures()
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
     * Returns the progress of a batch of translations.
     *
     * @param string $batch Batch ID
     * @return array{total: int, done: int, failed: int}|null Progress or NULL if the batch is unknown
     */
    public static function progress( string $batch ) : ?array
    {
        if( !( $batch = Bus::findBatch( $batch ) ) ) {
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
            if( RateLimiter::remaining( $this->key(), self::max() ) < 1 ) {
                throw new Throttled( max( 1, RateLimiter::availableIn( $this->key() ) ) );
            }

            Tenancy::run( $this->tenant, function() {

                if( !( $user = $this->user() ) || !Permission::can( 'page:save', $user ) || ( !Permission::can( 'page:add', $user )
                    && !PageVariant::withTrashed()->where( 'page_id', $this->id )->where( 'lang', $this->lang )->exists() )
                ) {
                    throw new Exception( 'Insufficient permissions' );
                }

                $keys = [];
                $used = function( array $list ) use ( &$keys ) {
                    array_push( $keys, ...$list );
                };

                Auth::setUser( $user );
                Resource::translatePage( $this->id, $this->lang, $user, Ai::translator( $user, fn( int $calls ) => $this->throttle( $calls ), $used ) );

                // cached chunks are only kept for retrying failed translations
                Ai::forget( $keys );
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
     */
    public function retryUntil() : \DateTimeInterface
    {
        return now()->addHours( 6 );
    }


    /**
     * Returns the key of the rate limiter for the tenant.
     */
    protected function key() : string
    {
        return self::limiter( $this->tenant );
    }


    /**
     * Returns the key of the rate limiter for the given tenant.
     *
     * @param string $tenant Tenant ID
     */
    protected static function limiter( string $tenant ) : string
    {
        return 'cms-translate:' . $tenant;
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
     * Reserves the provider calls of the tenant and stops the job if there are too many.
     *
     * @param int $calls Number of provider calls to make
     * @throws Throttled If the rate limit would be exceeded
     */
    protected function throttle( int $calls ) : void
    {
        self::reserve( $this->tenant, $calls );
    }


    /**
     * Reserves AI translation provider calls of the tenant.
     *
     * The calls are counted atomically first and given back if the limit is exceeded, so
     * concurrent translations can't exceed the limit together. If more calls are needed than allowed
     * per minute, they are only reserved if no other calls have been made in the current minute.
     *
     * @param string $tenant Tenant ID
     * @param int $calls Number of provider calls to make
     * @throws Throttled If the rate limit would be exceeded
     */
    public static function reserve( string $tenant, int $calls ) : void
    {
        $key = self::limiter( $tenant );
        $hits = RateLimiter::increment( $key, 60, $calls );

        if( $hits > self::max() && $hits > $calls )
        {
            RateLimiter::decrement( $key, 60, $calls );
            throw new Throttled( max( 1, RateLimiter::availableIn( $key ) ) );
        }
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

        $guard = config( 'auth.defaults.guard' );
        $provider = Auth::createUserProvider( config( "auth.guards.$guard.provider" ) );

        return $provider?->retrieveById( $this->userId );
    }
}

