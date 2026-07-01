<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Http\Middleware;

use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Watch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;


/**
 * Measures the wall-clock duration of read-only JSON:API requests and dispatches a Queried event.
 *
 * Instrumentation lives here rather than in the controller because the controller methods only
 * decorate an already-built response and have no request-lifecycle timing. Active when the watch
 * log is enabled or Pulse is recording JSON:API metrics; failures never break the response.
 */
class WatchJsonapi
{
    /**
     * Handles the request, dispatching a Queried event with the request duration and shape.
     */
    public function handle( Request $request, Closure $next ) : Response
    {
        $start = Watch::start( 'cms.jsonapi.watch', Queried::class );

        $response = $next( $request );

        Watch::dispatchWhen( 'cms.jsonapi.watch', Queried::class, fn() => new Queried(
            action: $this->action( $request ),
            durationMs: Watch::duration( $start ),
            domain: $this->domain( $request ),
            includes: $this->includes( $request ),
            tenant: Tenancy::value(),
        ) );

        return $response;
    }


    /**
     * Distinguishes a single-resource read from a collection search by the route URI: the "index"
     * route is the bare resource type ("cms/pages"), every other route binds the resource id and
     * therefore carries a "{…}" placeholder ("cms/pages/{page}", related and relationship routes).
     */
    protected function action( Request $request ) : string
    {
        $route = $request->route();
        $uri = $route instanceof Route ? $route->uri() : '';

        return str_contains( $uri, '{' ) ? 'jsonapi:read' : 'jsonapi:search';
    }


    /**
     * Returns the requested domain (the host) when multi-domain routing is enabled.
     *
     * The JSON:API routes are not bound to a "{domain}" parameter, so the domain is taken from
     * the request host the same way the page cache and sitemap resolve it.
     */
    protected function domain( Request $request ) : string
    {
        return config( 'cms.multidomain' ) ? $request->getHost() : '';
    }


    /**
     * Returns the comma-separated list of requested includes.
     */
    protected function includes( Request $request ) : string
    {
        $include = $request->query( 'include', '' );

        return is_string( $include ) ? $include : '';
    }
}
