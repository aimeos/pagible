<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Cache\RedisStore;
use Illuminate\Http\Response;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;


class PageCache
{
    /**
     * Invalidates complete-page cache entries without waiting for render leases.
     *
     * @param iterable<array{domain: string, path: string}> $routes
     */
    public static function invalidate( iterable $routes, string $tenant ) : void
    {
        $keys = [];

        foreach( $routes as $route ) {
            $keys[self::routeKey( $tenant, $route['domain'], $route['path'] )] = true;
        }

        if( $keys ) {
            self::forget( array_keys( $keys ) );
        }
    }


    /**
     * Returns a cached response or renders and stores it on a cache miss.
     *
     * A contending request receives a stale entry when available. On a cold miss,
     * it waits for the renderer and rechecks the cache before rendering itself.
     *
     * @param Closure(): mixed $renderFn
     */
    public static function remember( Closure $renderFn, Models\Page|string $page, string $domain = '' ) : mixed
    {
        $key = self::key( $page, $domain );
        $lock = self::renderLock( $key );

        if( !$lock->get() )
        {
            if( $response = self::cachedResponse( $key ) ) {
                return $response;
            }

            try {
                return $lock->block( self::lockLifetime() + 1, fn() => self::refresh( $key, $renderFn ) );
            } catch( LockTimeoutException ) {
                return self::cachedResponse( $key ) ?? $renderFn();
            }
        }

        try {
            return self::refresh( $key, $renderFn );
        } finally {
            $lock->release();
        }
    }


    /**
     * Returns a cached complete-page response.
     *
     * @param Models\Page|string $page Page model or route path
     */
    public static function response( Models\Page|string $page, string $domain = '', bool $fresh = false ) : ?Response
    {
        return self::cachedResponse( self::key( $page, $domain ), $fresh );
    }


    /**
     * Returns a cached response for an internal cache key.
     */
    private static function cachedResponse( string $key, bool $fresh = false ) : ?Response
    {
        if( !( $entry = self::get( $key, $fresh ) ) ) {
            return null;
        }

        $maxage = max( 0, $entry['freshUntil'] - time() );
        $expires = gmdate( 'D, d M Y H:i:s', $entry['freshUntil'] ) . ' GMT';

        return ( new Response( $entry['html'], 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', "public, s-maxage={$maxage}, max-age=0, must-revalidate" )
            ->header( 'Expires', $expires );
    }


    /**
     * Deletes cache keys in store-specific batches.
     *
     * @param list<string> $keys
     */
    private static function forget( array $keys ) : void
    {
        $repository = self::store();
        $store = $repository->getStore();
        $name = (string) config( 'cms.theme.cache', 'file' );
        $table = config( 'cache.stores.' . $name . '.table' );

        if( $store instanceof RedisStore )
        {
            $connection = $store->connection();
            $groups = [];

            foreach( $keys as $key ) {
                $groups[strstr( $key, '}', true ) ?: $key][] = $store->getPrefix() . $key;
            }

            if( $connection instanceof PhpRedisConnection
                && !$connection instanceof PhpRedisClusterConnection
            )
            {
                self::pipeline( $connection, $groups );
                return;
            }

            foreach( $groups as $group ) {
                foreach( array_chunk( $group, 500 ) as $chunk ) {
                    $connection->command( 'unlink', $chunk );
                }
            }

            return;
        }

        foreach( array_chunk( $keys, 500 ) as $chunk )
        {
            if( $store instanceof DatabaseStore && is_string( $table ) && $table !== '' )
            {
                $prefixed = array_map( fn( string $key ) => $store->getPrefix() . $key, $chunk );
                $store->getConnection()->table( $table )->whereIn( 'key', $prefixed )->delete();
            }
            elseif( $store instanceof MemcachedStore )
            {
                $prefixed = array_map( fn( string $key ) => $store->getPrefix() . $key, $chunk );
                $store->getMemcached()->deleteMulti( $prefixed );
            }
            else
            {
                $repository->deleteMultiple( $chunk );
            }
        }
    }

    /**
     * Returns a validated cached-page envelope.
     *
     * @return array{html: string, freshUntil: int}|null
     */
    private static function get( string $key, bool $fresh = false ) : ?array
    {
        $value = self::store()->get( $key );

        if( is_array( $value )
            && is_string( $value['html'] ?? null )
            && is_int( $value['freshUntil'] ?? null )
        ) {
            return !$fresh || $value['freshUntil'] > time() ? $value : null;
        }

        // Ignore cache values from versions before the envelope format. They will
        // naturally be replaced on the next render.
        return null;
    }


    /**
     * Returns the complete-page cache key for a page or route.
     */
    private static function key( Models\Page|string $page, string $domain = '' ) : string
    {
        if( $page instanceof Models\Page ) {
            $domain = $page->domain;
            $page = $page->path;
        }

        return self::routeKey( Tenancy::value(), $domain, $page );
    }


    /**
     * Returns the configured render-lock lifetime in seconds.
     */
    private static function lockLifetime() : int
    {
        return max( 1, (int) config( 'cms.theme.lock', 5 ) );
    }


    /**
     * Deletes grouped Redis keys in one pipeline.
     *
     * @param array<string, list<string>> $groups
     */
    private static function pipeline( PhpRedisConnection $connection, array $groups ) : void
    {
        $connection->pipeline( function( \Redis $pipeline ) use ( $groups ) {
            foreach( $groups as $group ) {
                foreach( array_chunk( $group, 500 ) as $chunk ) {
                    $pipeline->unlink( ...$chunk );
                }
            }
        } );
    }


    /**
     * Returns a tenant- and route-bound cache key with a bounded Redis hash slot.
     */
    private static function routeKey( string $tenant, string $domain, string $path ) : string
    {
        $slot = hash( 'sha256', $tenant );
        $route = hash( 'sha256', json_encode( [$domain, $path], JSON_THROW_ON_ERROR ) );
        $buckets = max( 1, min( 256, (int) config( 'cms.theme.buckets', 16 ) ) );
        $bucket = str_pad( dechex( hexdec( substr( $route, 0, 4 ) ) % $buckets ), 2, '0', STR_PAD_LEFT );

        return '{' . $slot . ':' . $bucket . '}:2:' . $route;
    }


    /**
     * Stores a page envelope through its fresh and stale lifetime.
     */
    private static function put( string $key, string $html, \DateTimeInterface $expires ) : void
    {
        $grace = max( 0, (int) config( 'cms.theme.stale', 10 ) );
        $freshUntil = $expires->getTimestamp();
        $staleUntil = $freshUntil + $grace;

        self::store()->put(
            $key,
            ['html' => $html, 'freshUntil' => $freshUntil],
            max( 1, $staleUntil - time() ),
        );
    }


    /**
     * Rechecks a fresh entry before rendering and conditionally caching a response.
     *
     * @param Closure(): mixed $renderFn
     */
    private static function refresh( string $key, Closure $renderFn ) : mixed
    {
        if( $response = self::cachedResponse( $key, true ) ) {
            return $response;
        }

        $response = $renderFn();
        self::storeResponse( $key, $response );

        return $response;
    }


    /**
     * Creates the lock shared by renderers and invalidators.
     */
    private static function renderLock( string $key ) : Lock
    {
        $store = self::store()->getStore();

        if( !$store instanceof LockProvider ) {
            throw new \LogicException( 'The configured CMS theme cache store does not support atomic locks.' );
        }

        return $store->lock(
            $key . ':render',
            self::lockLifetime(),
        );
    }


    /**
     * Returns the configured complete-page cache repository.
     */
    private static function store() : \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store( config( 'cms.theme.cache', 'file' ) );
    }


    /**
     * Stores a freshly rendered public response.
     */
    private static function storeResponse( string $key, mixed $response ) : void
    {
        if( !$response instanceof Response ) {
            return;
        }

        $headers = $response->headers;

        if( !$headers->hasCacheControlDirective( 'public' )
            || $headers->hasCacheControlDirective( 'private' )
            || $headers->hasCacheControlDirective( 'no-store' )
            || $headers->hasCacheControlDirective( 'no-cache' )
            || !( $expires = $response->getExpires() )
            || $expires->getTimestamp() <= time()
        ) {
            return;
        }

        self::put( $key, (string) $response->getContent(), $expires );
    }
}
