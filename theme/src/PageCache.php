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
        if( !( $entry = self::get( $key, $fresh ) ) ) {
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

        return $gzip ? $response->header( 'Content-Encoding', 'gzip' ) : $response;
    }


    /**
     * Returns a validated cached-page envelope.
     *
     * @return array{gzip: string, freshUntil: int}|null
     */
    private static function get( string $key, bool $fresh = false ) : ?array
    {
        $value = self::store()->get( $key );

        if( is_array( $value )
            && is_string( $value['gzip'] ?? null )
            && is_int( $value['freshUntil'] ?? null )
        ) {
            return !$fresh || $value['freshUntil'] > time() ? $value : null;
        }

        // Ignore values using other envelope formats. They will naturally be
        // replaced on the next render.
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
     * Returns a tenant- and route-bound cache key.
     */
    private static function routeKey( string $tenant, string $domain, string $path ) : string
    {
        return hash( 'sha256', json_encode( [$tenant, $domain, $path], JSON_THROW_ON_ERROR ) );
    }


    /**
     * Stores a page envelope through its fresh and stale lifetime.
     */
    private static function put( string $key, string $html, \DateTimeInterface $expires ) : void
    {
        $grace = max( 0, (int) config( 'cms.theme.stale', 10 ) );
        $freshUntil = $expires->getTimestamp();

        self::store()->put(
            $key,
            ['gzip' => gzencode( $html, 6 ), 'freshUntil' => $freshUntil],
            max( 1, $freshUntil + $grace - time() ),
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
