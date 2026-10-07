<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Exceptions\OverloadedException;
use Aimeos\Prisma\Exceptions\RateLimitException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Auth;
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
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;


    /** @var array<int, int> Delays in seconds before retrying after a provider error */
    public const BACKOFF = [10, 60, 300];


    /**
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param string $tenant Tenant ID
     * @param int|string|null $userId ID of the user who started the translation
     * @param string|null $batch ID of the batch to report the progress to
     */
    public function __construct( public string $id, public string $lang, public string $tenant,
        public int|string|null $userId, public ?string $batch = null )
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
    }


    /**
     * Records the progress of a batch of translations.
     *
     * @param string $batch Batch ID
     * @param int $total Number of translations in the batch
     */
    public static function batch( string $batch, int $total ) : void
    {
        foreach( ['total' => $total, 'done' => 0, 'failed' => 0] as $name => $value ) {
            Cache::put( self::key( 'batch', $batch . ':' . $name ), $value, now()->addDay() );
        }
    }


    /**
     * Returns the progress of a batch of translations.
     *
     * @param string $batch Batch ID
     * @return array{total: int, done: int, failed: int}|null Progress or NULL if the batch is unknown
     */
    public static function progress( string $batch ) : ?array
    {
        if( ( $total = Cache::get( self::key( 'batch', $batch . ':total' ) ) ) === null ) {
            return null;
        }

        return [
            'total' => (int) $total,
            'done' => (int) Cache::get( self::key( 'batch', $batch . ':done' ) ),
            'failed' => (int) Cache::get( self::key( 'batch', $batch . ':failed' ) ),
        ];
    }


    /**
     * Translates the page variant as the user who started the translation.
     */
    public function handle() : void
    {
        try
        {
            Tenancy::run( $this->tenant, function() {

                if( !( $user = $this->user() ) || !Permission::can( 'page:save', $user ) ) {
                    throw new Exception( 'Insufficient permissions' );
                }

                $exists = PageVariant::withTrashed()->where( 'page_id', $this->id )->where( 'lang', $this->lang )->exists();

                if( !$exists && !Permission::can( 'page:add', $user ) ) {
                    throw new Exception( 'Insufficient permissions' );
                }

                Auth::setUser( $user );
                Resource::translatePage( $this->id, $this->lang, $user, Ai::translator( $user, fn( int $calls ) => $this->throttle( $calls ) ) );
            } );
        }
        catch( Throttled $e )
        {
            if( $this->sync() ) {
                $this->finish( new Exception( 'Too many translations, please try again later' ) );
            }

            $this->release( $e->delay );
            return;
        }
        catch( RateLimitException|OverloadedException|ConnectException $e )
        {
            $key = self::key( 'retries', $this->uniqueId() );
            $retries = (int) Cache::get( $key ) + 1;

            if( !$this->sync() && $retries <= count( self::BACKOFF ) )
            {
                Cache::put( $key, $retries, now()->addDay() );
                $this->release( self::BACKOFF[$retries - 1] );
                return;
            }

            $this->finish( $e );
            return;
        }
        catch( \Throwable $e )
        {
            $this->finish( $e );
            return;
        }

        $this->finish();
    }


    /**
     * Allows retrying until the job is released too often because of the rate limit.
     */
    public function retryUntil() : \DateTimeInterface
    {
        return now()->addHours( 6 );
    }


    /**
     * Returns the ID which makes the job unique per page and language.
     */
    public function uniqueId() : string
    {
        return hash( 'sha256', $this->tenant . ':' . $this->id . ':' . $this->lang );
    }


    /**
     * Logs the failure, reports the progress and fails the job.
     *
     * @param \Throwable|null $e Error which occurred or NULL on success
     */
    protected function finish( ?\Throwable $e = null ) : void
    {
        Cache::forget( self::key( 'retries', $this->uniqueId() ) );

        if( $this->batch && Cache::has( self::key( 'batch', $this->batch . ':total' ) ) ) {
            Cache::increment( self::key( 'batch', $this->batch . ( $e ? ':failed' : ':done' ) ) );
        }

        if( !$e ) {
            return;
        }

        Log::error( 'Page translation failed', [
            'page' => $this->id,
            'lang' => $this->lang,
            'tenant' => $this->tenant,
            'error' => $e->getMessage(),
        ] );

        if( $this->sync() ) {
            throw $e;
        }

        $this->fail( $e );
    }


    /**
     * Returns the cache key for the given type and ID.
     *
     * @param string $type Key type, "batch" or "retries"
     * @param string $id Batch or job ID
     * @return string Cache key
     */
    protected static function key( string $type, string $id ) : string
    {
        return 'cms-translate-' . $type . ':' . $id;
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
     * Counts the provider calls of the tenant and stops the job if there are too many.
     *
     * @param int $calls Number of provider calls to make
     * @throws Throttled If the rate limit would be exceeded
     */
    protected function throttle( int $calls ) : void
    {
        $key = 'cms-translate:' . $this->tenant;
        $max = max( 1, (int) config( 'cms.ai.ratelimit', 60 ) );

        if( RateLimiter::remaining( $key, $max ) < min( $calls, $max ) ) {
            throw new Throttled( max( 1, RateLimiter::availableIn( $key ) ) );
        }

        for( $i = 0; $i < $calls; $i++ ) {
            RateLimiter::hit( $key, 60 );
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

