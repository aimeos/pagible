<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Models\Page;
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
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle( Request $request, Closure $next )
    {
        // Avoid all metric work unless an optional observer is installed. $start
        // doubles as the "record this request" flag and the timer.
        $start = Event::hasListeners( Observed::class )
            ? hrtime( true ) : null;

        // Computed lazily so a watch-off non-cacheable request (query string, cookie,
        // POST) builds neither.
        $path = $domain = null;

        if( $request->isMethod( 'GET' ) && empty( $request->query() )
            && !$request->hasCookie( config( 'session.cookie' ) )
        ) {
            $path = trim( $request->getPathInfo(), '/' );
            $domain = config( 'cms.multidomain' ) ? $request->getHost() : '';

            if( ( $html = Cache::store( config( 'cms.theme.cache', 'file' ) )->get( Page::key( $path, $domain ) ) ) !== null ) {
                if( $start !== null ) {
                    $this->observe( $path, $domain, 200, $start );
                }
                return $this->response( $html );
            }
        }

        $response = $next( $request );

        // A publicly cacheable page is byte-identical for every visitor, so the
        // rendered cache-miss response must not carry per-visitor cookies (session,
        // XSRF). Stripping them keeps the response and the stored HTML cacheable by a
        // CDN. Uncached pages (private response) and editor previews keep their cookies.
        if( $response instanceof Response
            && str_contains( (string) $response->headers->get( 'Cache-Control' ), 'public' )
        ) {
            foreach( $response->headers->getCookies() as $cookie ) {
                $response->headers->removeCookie( $cookie->getName(), $cookie->getPath(), $cookie->getDomain() );
            }
        }

        // Skip status extraction and dispatch entirely when no observer is installed.
        if( $start !== null )
        {
            $status = $response instanceof \Symfony\Component\HttpFoundation\Response
                ? $response->getStatusCode() : 200;

            $this->observe(
                $path ?? trim( $request->getPathInfo(), '/' ),
                $domain ?? ( config( 'cms.multidomain' ) ? $request->getHost() : '' ),
                $status,
                $start
            );
        }

        return $response;
    }


    /**
     * Builds and dispatches the page-request observation.
     *
     * @param string $path Requested path without surrounding slashes
     * @param string $domain Requested domain, empty unless multi-domain routing is on
     * @param int $status HTTP status code of the response
     * @param int|float $start High-resolution start time from hrtime()
     */
    protected function observe( string $path, string $domain, int $status, int|float $start ) : void
    {
        Watch::observe(
            source: 'request',
            action: 'theme:view',
            durationMs: Watch::duration( $start ),
            dimensions: [
                'path' => $status === 200 ? '/' . $path : '*',
                'domain' => $status === 200 ? $domain : '',
                'status' => $status,
            ],
            sample: true,
        );
    }


    /**
     * Builds the public, CDN-cacheable response for stored page HTML.
     *
     * The stored value ends with the page's expiry timestamp (29 chars); the
     * shared cache lifetime is derived from it so the edge and the server cache
     * expire together.
     *
     * @param string $html Cached page HTML with trailing expiry marker
     * @return \Illuminate\Http\Response
     */
    protected function response( string $html ) : Response
    {
        $expires = substr( $html, -29 );
        $maxage = max( 0, strtotime( $expires ) - time() );

        return ( new Response( $html, 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', "public, s-maxage={$maxage}, max-age=0, must-revalidate" )
            ->header( 'Expires', $expires );
    }
}
