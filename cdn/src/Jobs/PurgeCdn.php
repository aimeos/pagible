<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Cdn;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\UnsupportedProxyOperationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;


/**
 * Purges URLs from one CDN client, the credentials are read from the configuration when the job runs.
 *
 * Equal purges which are still queued are skipped, e.g. removing all content for each batch of a large change.
 */
class PurgeCdn implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** Seconds until the lock expires if the queued job is lost */
    public int $uniqueFor = 3600;


    /**
     * @param string $client Name of the configured CDN client
     * @param list<string> $urls Absolute URLs or only Cdn::ALL to remove all content
     */
    public function __construct( public string $client, public array $urls )
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
    }


    /**
     * @return list<int> Seconds to wait before retrying
     */
    public function backoff() : array
    {
        return [10, 60, 300, 900];
    }


    public function handle() : void
    {
        // Removed or incomplete clients don't receive queued purges any more
        if( $config = Cdn::clients()[$this->client] ?? null ) {
            $this->flush( $config, $this->urls );
        }
    }


    /**
     * Returns the ID of equal purges.
     */
    public function uniqueId() : string
    {
        return $this->client . ':' . md5( implode( "\n", $this->urls ) );
    }


    /**
     * Purges the URLs from the client.
     *
     * @param array<string, mixed> $config Client configuration
     * @param list<string> $urls Absolute URLs or only Cdn::ALL to remove all content
     * @throws ExceptionCollection If purging failed
     * @throws UnsupportedProxyOperationException If the client can't remove all content
     */
    private function flush( array $config, array $urls ) : void
    {
        $invalidator = Cdn::invalidator( $config );

        if( in_array( Cdn::ALL, $urls, true ) ) {
            $invalidator->clearCache();
        }
        else
        {
            foreach( $urls as $url ) {
                $invalidator->invalidatePath( $url );
            }
        }

        $invalidator->flush();
    }
}
