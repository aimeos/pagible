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
use Symfony\Component\HttpFoundation\AcceptHeader;


class PageCache
{
    /**
     * Tests whether a response is publicly cacheable (public, not private, not no-store).
     */
    public static function cacheable( Response $response ) : bool
    {
        $headers = $response->headers;

        return $headers->hasCacheControlDirective( 'public' )
            && !$headers->hasCacheControlDirective( 'private' )
            && !$headers->hasCacheControlDirective( 'no-store' );
    }


    /**
     * Invalidates the cached responses for tenant- and domain-bound page paths.
     *
     * @param list<string> $paths
     */
    public static function invalidate( string $domain, array $paths, string $tenant ) : void
    {
        $keys = array_map(
            fn( string $path ) => self::routeKey( $tenant, $domain, $path ),
            $paths,
        );

        if( $keys ) {
            self::store()->deleteMultiple( array_values( array_unique( $keys ) ) );
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
        $entry = self::store()->get( $key );

        // Values using other envelope formats are ignored and replaced on the next render.
        if( !is_array( $entry )
            || !is_string( $entry['gzip'] ?? null )
            || !is_int( $entry['freshUntil'] ?? null )
            || $fresh && $entry['freshUntil'] <= time()
        ) {
            return null;
        }

        $maxage = max( 0, $entry['freshUntil'] - time() );
        $expires = gmdate( 'D, d M Y H:i:s', $entry['freshUntil'] ) . ' GMT';

        $gzip = AcceptHeader::fromString( request()->headers->get( 'Accept-Encoding' ) )->get( 'gzip' )?->getQuality() > 0;
        $content = $gzip ? (string) $entry['gzip'] : gzdecode( (string) $entry['gzip'] );

        if( !is_string( $content ) ) {
            return null;
        }

        $response = ( new Response( $content, 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', "public, s-maxage={$maxage}, max-age=0, must-revalidate" )
            ->header( 'Expires', $expires )
            ->header( 'Vary', 'Accept-Encoding' );

        if( is_string( $entry['lang'] ?? null ) && $entry['lang'] !== '' ) {
            $response->header( 'Content-Language', $entry['lang'] );
        }

        return $gzip ? $response->header( 'Content-Encoding', 'gzip' ) : $response;
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
     * Returns a tenant- and route-bound cache key.
     */
    private static function routeKey( string $tenant, string $domain, string $path ) : string
    {
        return hash( 'sha256', json_encode( [$tenant, $domain, $path], JSON_THROW_ON_ERROR ) );
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

        if( !self::cacheable( $response )
            || $response->headers->hasCacheControlDirective( 'no-cache' )
            || !( $expires = $response->getExpires() )
            || ( $freshUntil = $expires->getTimestamp() ) <= time()
        ) {
            return;
        }

        $grace = max( 0, (int) config( 'cms.theme.stale', 10 ) );
        $lang = (string) $response->headers->get( 'Content-Language' );

        self::store()->put(
            $key,
            ['gzip' => gzencode( (string) $response->getContent(), 6 ), 'freshUntil' => $freshUntil, 'lang' => $lang],
            max( 1, $freshUntil + $grace - time() ),
        );
    }
}
