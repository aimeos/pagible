<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\AiError;
use Aimeos\Cms\Exceptions\ThrottledException;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Translation\Limit;
use Aimeos\Cms\Translation\Translator;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;


/**
 * Translates the source variant of a page into one language variant.
 *
 * Rate limit, overload and connection errors are retried with growing delays,
 * other errors fail at once. The provider calls are throttled per tenant.
 */
class TranslatePage implements ShouldBeUnique, ShouldQueue
{
    use ActsAsUser;
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RoutesQueue;


    /**
     * Seconds a translation is retried before it fails.
     */
    public const RETRY = 6 * 3600;

    /**
     * Extra seconds the unique lock outlives the retry time.
     */
    private const MARGIN = 600;


    /**
     * Retry delays in seconds.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Number of provider errors after which the job fails.
     */
    public int $maxExceptions = 4;

    /**
     * Seconds until a lost job can be queued again.
     */
    public int $uniqueFor = self::RETRY + self::MARGIN;


    /**
     * @param string $id Page UUID
     * @param string $lang Language code
     * @param string $tenant Tenant ID
     * @param Authenticatable|null $user User or NULL inside Permission::system()
     * @param string|null $guard Auth guard or NULL for the default one
     */
    public function __construct( public string $id, public string $lang, public string $tenant, ?Authenticatable $user = null, ?string $guard = null )
    {
        $this->routeQueue();
        $this->remember( $user, $guard );
    }


    /**
     * Logs the failed translation.
     *
     * @param \Throwable $e Error
     */
    public function failed( \Throwable $e ) : void
    {
        AiError::log( 'Page translation failed', $e, ['page' => $this->id, 'lang' => $this->lang, 'tenant' => $this->tenant] );
    }


    /**
     * Translates the page variant as the user who started the translation.
     */
    public function handle() : void
    {
        try
        {
            Tenancy::run( $this->tenant, function() {
                // cheap check before loading the page, doesn't count as attempt
                $wait = Limit::wait();

                if( $wait > 0 ) {
                    throw new ThrottledException( $wait );
                }

                // trashed or deleted after queuing
                if( !PageView::whereKey( $this->id )->exists() ) {
                    return;
                }

                // Variants::translate() checks the permissions
                $this->acting( fn( ?Authenticatable $user ) => Variants::translate( $this->id, $this->lang, $user, Translator::for( $user ) ) );
            } );
        }
        catch( ThrottledException $e )
        {
            if( $this->sync() ) {
                throw $e;
            }

            // spread retries over the time the waiting jobs need, so they don't all run again in the next minute
            $this->release( $e->delay + Limit::jitter( $this->batch()->pendingJobs ?? 1 ) );
        }
        catch( \Throwable $e )
        {
            // retried by the queue with back-off
            if( $this->sync() || AiError::retryable( $e ) ) {
                throw $e;
            }

            $this->fail( $e );
        }
    }


    /**
     * Returns the jobs not queued already.
     *
     * Batches only release unique locks, so they are acquired here and unlock() releases them if queuing fails.
     *
     * @param array<int, array{0: string, 1: string}> $pairs Page ID and language pairs
     * @param string $tenant Tenant ID
     * @param Authenticatable|null $user User or NULL inside Permission::system()
     * @param int $index Queue position of the first job to spread the delays
     * @return array<int, self> Jobs with acquired unique lock
     */
    public static function jobs( array $pairs, string $tenant, ?Authenticatable $user, int $index ) : array
    {
        $lock = self::lock();
        $jobs = [];

        foreach( $pairs as [$id, $lang] )
        {
            $delay = Limit::delay( $index + count( $jobs ) );
            $job = ( new self( $id, $lang, $tenant, $user ) )->delay( $delay );
            // locks of lost jobs expire after the retry time
            $job->uniqueFor += $delay;

            if( $lock->acquire( $job ) ) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }


    /**
     * Retry time, starting when the job is due so delayed jobs get the same time.
     */
    public function retryUntil() : \DateTimeInterface
    {
        return now()->addSeconds( is_int( $this->delay ) ? $this->delay : 0 )->addSeconds( self::RETRY );
    }


    /**
     * Returns the ID which makes the job unique per page and language.
     *
     * UUIDs and languages contain no ":", so tenants can't collide; changing the format strands existing locks.
     */
    public function uniqueId() : string
    {
        return hash( 'sha256', $this->tenant . ':' . $this->id . ':' . $this->lang );
    }


    /**
     * Releases the unique locks of unqueued jobs.
     *
     * @param array<int, self> $jobs Jobs from jobs()
     */
    public static function unlock( array $jobs ) : void
    {
        $lock = self::lock();

        foreach( $jobs as $job ) {
            $lock->release( $job );
        }
    }


    /**
     * Tests if the job runs synchronously.
     *
     * @return bool TRUE if synchronous
     */
    protected function sync() : bool
    {
        return !$this->job || $this->job instanceof SyncJob;
    }


    /**
     * Returns the lock which keeps the jobs unique.
     *
     * @return UniqueLock Lock on the default cache store
     */
    private static function lock() : UniqueLock
    {
        return new UniqueLock( app( Repository::class ) );
    }
}
