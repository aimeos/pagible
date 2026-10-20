<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\CdnBatch;
use Aimeos\Cms\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;


/**
 * Queues the purges of the URLs collected during the delay at once.
 */
class FlushCdn implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RoutesQueue;

    /** ID of the tenant whose collected URLs are purged */
    public string $tenant;

    public int $tries = 3;


    /**
     * @param string|null $tenant ID of the tenant whose collected URLs are purged, NULL for the current tenant
     */
    public function __construct( ?string $tenant = null )
    {
        $this->tenant = $tenant ?? Tenancy::value();
        $this->routeQueue();
    }


    /**
     * @return list<int> Seconds to wait before retrying
     */
    public function backoff() : array
    {
        return [10, 60];
    }


    /**
     * Queues the purge jobs for the collected URLs, the ones of a failed attempt are queued again by the retry.
     */
    public function handle() : void
    {
        if( $batch = CdnBatch::take( $this->tenant ) )
        {
            Cdn::queue( $batch['urls'], max( 0, now()->getTimestamp() - $batch['since'] ), $this->tenant );
            CdnBatch::done( $this->tenant );
        }
    }
}
