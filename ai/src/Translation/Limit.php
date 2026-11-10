<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Translation;

use Aimeos\Cms\Exceptions\ThrottledException;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;


/**
 * Limits the queued AI translation calls per minute of each tenant.
 */
final class Limit
{
    /**
     * Seconds of the rate limit window.
     */
    private const MINUTE = 60;

    /**
     * Cache key suffix for the end of the block after a burst.
     */
    private const UNTIL = ':until';


    /**
     * Returns the maximum provider calls within the time span.
     *
     * @param int $seconds Time span in seconds
     * @return int Maximum provider calls
     */
    public static function capacity( int $seconds ) : int
    {
        return self::max() * intdiv( $seconds, self::MINUTE );
    }


    /**
     * Returns the delay spreading queued calls over the minutes the rate limit allows.
     *
     * @param int $index Queue position, starting at 0
     * @return int Seconds until the call is due
     */
    public static function delay( int $index ) : int
    {
        return intdiv( $index, self::max() ) * self::MINUTE;
    }


    /**
     * Returns a random delay spreading retries over the minutes the waiting calls need.
     *
     * @param int $waiting Number of waiting calls
     * @return int Additional seconds
     */
    public static function jitter( int $waiting ) : int
    {
        return random_int( 0, (int) ceil( max( 1, $waiting ) / self::max() ) * self::MINUTE );
    }


    /**
     * Reserves provider calls atomically, bursts above the limit only in an unused minute and block the following ones.
     *
     * @param int $calls Number of provider calls
     * @throws ThrottledException If the rate limit would be exceeded
     */
    public static function reserve( int $calls ) : void
    {
        $key = self::key();
        $max = self::max();

        // safe, concurrent bursts are rejected by the counter until the block is set
        $wait = self::blocked( $key );

        if( $wait > 0 ) {
            throw new ThrottledException( $wait );
        }

        $hits = RateLimiter::increment( $key, self::MINUTE, $calls );

        if( $hits > $max && $hits > $calls )
        {
            RateLimiter::decrement( $key, self::MINUTE, $calls );
            throw new ThrottledException( max( 1, RateLimiter::availableIn( $key ) ) );
        }

        if( $hits > $max )
        {
            $wait = RateLimiter::availableIn( $key ) + ( (int) ceil( $hits / $max ) - 1 ) * self::MINUTE;
            self::cache()->put( $key . self::UNTIL, now()->getTimestamp() + $wait, $wait );
        }
    }


    /**
     * Returns the seconds until calls of the tenant are allowed again.
     *
     * @return int Seconds or 0 if allowed
     */
    public static function wait() : int
    {
        $key = self::key();

        $wait = self::blocked( $key );

        if( $wait > 0 ) {
            return $wait;
        }

        return RateLimiter::remaining( $key, self::max() ) < 1 ? max( 1, RateLimiter::availableIn( $key ) ) : 0;
    }


    /**
     * Returns the seconds calls are blocked after a burst.
     *
     * @param string $key Rate limiter key
     * @return int Seconds, zero or negative if not blocked
     */
    private static function blocked( string $key ) : int
    {
        return (int) self::cache()->get( $key . self::UNTIL, 0 ) - now()->getTimestamp();
    }


    /**
     * Returns the rate limiter cache store, so the block is shared like the counter.
     */
    private static function cache() : Repository
    {
        return Cache::store( config( 'cache.limiter' ) );
    }


    /**
     * Returns the rate limiter key of the current tenant.
     */
    private static function key() : string
    {
        return Tenancy::key( 'cms-translate' );
    }


    /**
     * Returns the maximum provider calls per minute.
     */
    private static function max() : int
    {
        return max( 1, (int) config( 'cms.ai.ratelimit' ) );
    }
}
