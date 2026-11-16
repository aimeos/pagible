<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Remembers values fetched once per key in a cache property of the object.
 */
trait JoomlaRemembers
{
    /**
     * Returns the value fetched once for the cache key, NULL values are cached too unlike with "??=".
     *
     * @template T
     * @param array<array-key, T> $cache Cached values by key
     * @param int|string $key Cache key
     * @param \Closure(): T $fetch Fetches the value
     * @return T Cached or fetched value
     */
    protected function remember( array &$cache, int|string $key, \Closure $fetch ): mixed
    {
        if( !array_key_exists( $key, $cache ) ) {
            $cache[$key] = $fetch();
        }

        return $cache[$key];
    }
}
