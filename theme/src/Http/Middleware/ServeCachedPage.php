<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Aimeos\Cms\Events\CmsRequest;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\PageCache;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;


/**
 * Serves a cached page directly to anonymous visitors, before any session or
 * cookie middleware runs.
 *
 * The cached HTML is final and static (CSP hashes resolved, no CSRF token), so
 * it is returned verbatim with a "public" cache policy and no Set-Cookie header.
 * That lets a CDN cache and serve it. Requests that carry a session cookie (i.e.
 * potential editors) or that have query parameters fall through to the full stack.
 */
class ServeCachedPage
{
    private static ?Closure $bypassCallback = null;


    /**
     * Adds authentication indicators used to bypass complete-page caching.
     */
    public static function bypassUsing( ?Closure $callback ) : void
    {
        self::$bypassCallback = $callback;
    }


    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle( Request $request, Closure $next )
    {
        // Gate on the CMS_THEME_WATCH flag first so the cold path short-circuits before
        // the listener lookup and sampling. $start doubles as the "record this request"
        // flag and the timer.
        $start = config( 'cms.theme.watch' ) && Event::hasListeners( CmsRequest::class ) && Watch::sampled()
            ? hrtime( true ) : null;

        $domain = config( 'cms.multidomain' ) ? $request->getHost() : '';
        $canonical = Origin::matches( $request );
        $path = null;

        if( $canonical && self::cachable( $request ) )
        {
            $path = trim( $request->getPathInfo(), '/' );

            if( $response = PageCache::response( $path, $domain, fresh: true ) ) {
                if( $start !== null ) {
                    $this->watch( $path, $domain, 200, $start );
                }
                return $response;
            }

            // Resolve the lightweight route only on cache misses, before the "web"
            // middleware starts the session.
            $page = Nav::page( $path, $domain );
            $request->attributes->set( 'cms.page', $page );

            $response = match( true ) {
                !$page => new Response( '', 404 ),
                $page->access_exists => $next( $request ),
                $page->cache > 0 => PageCache::remember( fn() => $next( $request ), $page ),
                default => $next( $request ),
            };
        }
        else {
            $response = $next( $request );
        }

        // A publicly cacheable page is byte-identical for every visitor, so the
        // rendered cache-miss response must not carry per-visitor cookies (session,
        // XSRF). Stripping them keeps the response and the stored HTML cacheable by a
        // CDN. Uncached pages (private response) and editor previews keep their cookies.
        if( $response instanceof Response
            && $response->headers->hasCacheControlDirective( 'public' )
            && !$response->headers->hasCacheControlDirective( 'private' )
            && !$response->headers->hasCacheControlDirective( 'no-store' )
        ) {
            foreach( $response->headers->getCookies() as $cookie ) {
                $response->headers->removeCookie( $cookie->getName(), $cookie->getPath(), $cookie->getDomain() );
            }
        }

        // Skip status extraction and dispatch entirely when watch is off or unsampled.
        if( $start !== null )
        {
            $status = $response instanceof \Symfony\Component\HttpFoundation\Response
                ? $response->getStatusCode() : 200;

            $this->watch(
                $path ?? trim( $request->getPathInfo(), '/' ),
                $domain,
                $status,
                $start
            );
        }

        return $response;
    }


    /**
     * Tests whether the request may use the anonymous complete-page cache.
     */
    private static function cachable( Request $request ) : bool
    {
        if( !$request->isMethod( 'GET' )
            || $request->query()
            || Auth::guard()->hasUser()
            || $request->hasCookie( config( 'session.cookie' ) )
            || $request->headers->has( 'Authorization' )
        ) {
            return false;
        }

        return !self::$bypassCallback
            || !(bool) ( self::$bypassCallback )( $request );
    }
    /**
     * Builds and dispatches the page-request watch event; the caller has already
     * confirmed watch is enabled and sampled.
     *
     * @param string $path Requested path without surrounding slashes
     * @param string $domain Requested domain, empty unless multi-domain routing is on
     * @param int $status HTTP status code of the response
     * @param int|float $start High-resolution start time from hrtime()
     */
    protected function watch( string $path, string $domain, int $status, int|float $start ) : void
    {
        Watch::fire( fn() => new CmsRequest(
            path: $path,
            domain: $domain,
            status: $status,
            durationMs: Watch::duration( $start ),
            tenant: Tenancy::value(),
        ) );
    }
}
