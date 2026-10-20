<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use FOS\HttpCache\CacheInvalidator;
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
    use RoutesQueue;

    /** ID of the tenant the URLs belong to for the metrics */
    public string $tenant;

    public int $tries = 5;

    /** Seconds until the lock expires if the queued job is lost */
    public int $uniqueFor = 3600;


    /**
     * @param string $client Name of the configured CDN client
     * @param list<string> $urls Absolute URLs or only Cdn::ALL to remove all content
     * @param string|null $server Address of the one server to purge, NULL for all servers of the client
     * @param string|null $tenant ID of the tenant the URLs belong to for the metrics, NULL for the current tenant
     */
    public function __construct( public string $client, public array $urls, public ?string $server = null, ?string $tenant = null )
    {
        $this->tenant = $tenant ?? Tenancy::value();
        $this->routeQueue();
    }


    /**
     * @return list<int> Seconds to wait before retrying
     */
    public function backoff() : array
    {
        return [10, 60, 300, 900];
    }


    /**
     * Purges the URLs from the client.
     *
     * @throws ExceptionCollection If purging failed
     * @throws UnsupportedProxyOperationException If the client can't remove all content
     */
    public function handle() : void
    {
        // Removed or incomplete clients don't receive queued purges any more
        if( !( $config = Cdn::clients()[$this->client] ?? null ) ) {
            return;
        }

        if( $this->server !== null )
        {
            // Removed servers don't receive queued purges any more
            if( !in_array( $this->server, Cdn::servers( $config ), true ) ) {
                return;
            }

            $config['servers'] = [$this->server];
        }

        $start = hrtime( true );
        $success = false;

        try
        {
            try {
                $invalidator = Cdn::invalidator( $config );
            }
            catch( \Throwable $e )
            {
                // Configuration errors aren't fixed by retrying, so queued jobs fail at once
                if( !$this->job ) {
                    throw $e;
                }

                $this->fail( $e );
                return;
            }

            $this->purge( $invalidator, $config );
            $success = true;
        }
        finally
        {
            $this->observe( $start, $success );
        }
    }


    /**
     * Logs purges which failed after the last retry, throttled per client and error.
     *
     * The message isn't logged because it differs for each URL, the failed job contains all details.
     */
    public function failed( ?\Throwable $e ) : void
    {
        $fields = ['client' => $this->client, 'server' => $this->server, 'error' => $e ? $e::class : null];
        Watch::warnOnce( 'cms.cdn.purge_failed', array_filter( $fields, fn( $value ) => $value !== null ) );
    }


    /**
     * Returns the ID of equal purges.
     */
    public function uniqueId() : string
    {
        return $this->client . ':' . $this->server . ':' . md5( implode( "\n", $this->urls ) );
    }


    /**
     * Bans the URL including all its query strings, URLs with query strings are only purged themselves.
     */
    private function ban( CacheInvalidator $invalidator, string $url ) : void
    {
        $parts = parse_url( $url );

        if( isset( $parts['query'] ) || !isset( $parts['host'] ) ) {
            $invalidator->invalidatePath( $url );
            return;
        }

        $invalidator->invalidateRegex( '^' . preg_quote( $parts['path'] ?? '/' ) . '(\\?|$)', null, [$parts['host']] );
    }


    /**
     * Records the duration and result of the purge for the CMS metrics, e.g. in Laravel Pulse.
     *
     * @param int|float $start High-resolution start time from hrtime()
     */
    private function observe( int|float $start, bool $success ) : void
    {
        Watch::observe(
            source: 'cdn',
            action: in_array( Cdn::ALL, $this->urls, true ) ? 'cdn:clear' : 'cdn:purge',
            durationMs: Watch::duration( $start ),
            tenant: $this->tenant,
            dimensions: ['client' => $this->client, 'success' => $success],
        );
    }


    /**
     * Sends the purge requests of the URLs to the client.
     *
     * @param array<string, mixed> $config Client configuration
     */
    private function purge( CacheInvalidator $invalidator, array $config ) : void
    {
        if( in_array( Cdn::ALL, $this->urls, true ) ) {
            $invalidator->clearCache();
        }
        else
        {
            $supports = Cdn::supports( $config, $invalidator );
            $queries = $supports['queries'] ?? false;
            $refresh = $supports['refresh'] ?? false;

            foreach( $this->urls as $url )
            {
                if( $queries ) {
                    $this->ban( $invalidator, $url );
                }

                // Refreshing fetches the new content at once, so the next visitors don't all miss the cache
                if( $refresh ) {
                    $invalidator->refreshPath( $url );
                } elseif( !$queries ) {
                    $invalidator->invalidatePath( $url );
                }
            }
        }

        $invalidator->flush();
    }
}
