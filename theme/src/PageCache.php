<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Closure;
use Illuminate\Http\Response;
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
            self::store()->deleteMultiple( array_keys( $keys ) );
        }
    }


    /**
     * Returns a cached response or renders and stores it on a cache miss.
     *
     * A contending request receives a stale entry when available. On a cold miss,
     * it waits for the renderer and rechecks the cache before rendering itself.
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


    private static function lockLifetime() : int
    {
        return max( 1, (int) config( 'cms.theme.lock', 5 ) );
    }


    private static function routeKey( string $tenant, string $domain, string $path ) : string
    {
        return hash( 'sha256', json_encode( [$tenant, $domain, $path], JSON_THROW_ON_ERROR ) );
    }


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
