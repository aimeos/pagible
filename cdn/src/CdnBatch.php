<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\Cache;


/**
 * Collects the URLs changed during the delay in the cache, so they are purged together afterwards.
 *
 * Collected URLs are moved to a separate processing entry while they are queued, which can take longer than the
 * lock lasts. If queueing fails, the processing entry is taken again by the retry of the flush job.
 */
class CdnBatch
{
    /** Cache key prefix of the URLs collected during the delay */
    public const KEY = 'cms-cdn-batch:';

    /** Maximum number of URLs collected during the delay, which keeps the cache entry small */
    public const MAX = 2000;

    /** Seconds after the delay until collected URLs are flushed again if the flush job was lost */
    public const GRACE = 300;

    /** Seconds the lock is held at most and seconds to wait for it */
    private const LOCK = 10;
    private const WAIT = 5;

    /** Seconds the URLs are kept in addition to the delay and grace period, e.g. if the queue is down */
    private const TTL = 3600;


    /**
     * Adds the URLs to the batch of the tenant.
     *
     * @param string $tenant ID of the tenant the URLs belong to
     * @param list<string> $urls Absolute URLs
     * @param int $delay Seconds to collect the URLs
     * @return array{new: bool, overflow: list<string>} If the batch is new and needs a flush job, and the URLs of
     *  the batch which must be purged at once because the cache rejected them
     */
    public static function add( string $tenant, array $urls, int $delay ) : array
    {
        $key = self::KEY . $tenant;

        return self::lock( $key, function() use ( $delay, $key, $urls ) : array {
            $batch = Cache::get( $key );
            $list = is_array( $batch ) ? (array) ( $batch['urls'] ?? [] ) : [];
            $since = is_array( $batch ) ? (int) ( $batch['since'] ?? 0 ) : 0;

            // The first URLs start the delay, a lost flush job is replaced after the grace period
            if( $new = ( $now = now()->getTimestamp() ) - $since > $delay + self::GRACE ) {
                $since = $now;
            }

            $urls = array_values( array_unique( [...$list, ...$urls] ) );

            // Cache stores reject too large entries, e.g. Memcached above 1MB, so large batches are purged at once
            if( count( $urls ) <= self::MAX && Cache::put( $key, ['since' => $since, 'urls' => $urls], $delay + self::GRACE + self::TTL ) )
            {
                return ['new' => $new, 'overflow' => []];
            }

            Cache::forget( $key );
            return ['new' => false, 'overflow' => $urls];
        } );
    }


    /**
     * Removes the processing entry after its URLs have been queued.
     *
     * @param string $tenant ID of the tenant whose URLs have been queued
     */
    public static function done( string $tenant ) : void
    {
        Cache::forget( self::KEY . $tenant . ':flush' );
    }


    /**
     * Moves the collected URLs and the ones of a failed flush to the processing entry and returns them.
     *
     * @param string $tenant ID of the tenant whose URLs are purged
     * @return array{since: int, urls: list<string>}|null Timestamp of the first change and the URLs, NULL if none
     */
    public static function take( string $tenant ) : ?array
    {
        $key = self::KEY . $tenant;

        return self::lock( $key, function() use ( $key ) : ?array {
            $urls = [];
            $since = PHP_INT_MAX;

            foreach( [Cache::get( $key . ':flush' ), Cache::get( $key )] as $entry )
            {
                if( is_array( $entry ) )
                {
                    $urls = [...$urls, ...array_filter( (array) ( $entry['urls'] ?? [] ), is_string( ... ) )];
                    $since = min( $since, (int) ( $entry['since'] ?? 0 ) );
                }
            }

            if( !$urls = array_values( array_unique( $urls ) ) ) {
                return null;
            }

            $batch = ['since' => $since, 'urls' => $urls];
            Cache::put( $key . ':flush', $batch, self::TTL );
            Cache::forget( $key );

            return $batch;
        } );
    }


    /**
     * Runs the callback while holding the lock of the batch.
     *
     * @template T
     * @param \Closure(): T $fcn Callback changing the batch
     * @return T Result of the callback
     */
    private static function lock( string $key, \Closure $fcn ) : mixed
    {
        return Cache::lock( $key . ':lock', self::LOCK )->block( self::WAIT, $fcn );
    }
}
