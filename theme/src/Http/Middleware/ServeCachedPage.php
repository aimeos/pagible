<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Aimeos\Cms\Models\Page;


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
        if( $request->isMethod( 'GET' ) && empty( $request->query() )
            && !$request->hasCookie( config( 'session.cookie' ) )
        ) {
            $domain = config( 'cms.multidomain' ) ? $request->getHost() : '';
            $key = Page::key( trim( $request->getPathInfo(), '/' ), $domain );

            if( ( $html = Cache::store( config( 'cms.theme.cache', 'file' ) )->get( $key ) ) !== null ) {
                return $this->response( $html );
            }
        }

        return $next( $request );
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
