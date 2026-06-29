<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Http\Middleware;

use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;


/**
 * Measures the wall-clock duration of read-only JSON:API requests and dispatches a Queried event.
 *
 * Instrumentation lives here rather than in the controller because the controller methods only
 * decorate an already-built response and have no request-lifecycle timing. Active only when
 * "cms.jsonapi.watch" is enabled; a failure never breaks the response.
 */
class WatchJsonapi
{
    /**
     * Handles the request, dispatching a Queried event with the request duration and shape.
     */
    public function handle( Request $request, Closure $next ) : Response
    {
        $start = hrtime( true );

        $response = $next( $request );

        if( config( 'cms.jsonapi.watch', false ) )
        {
            try {
                event( new Queried(
                    action: $this->action( $request ),
                    durationMs: ( hrtime( true ) - $start ) / 1e6,
                    domain: $this->domain( $request ),
                    count: $this->count( $response ),
                    includes: $this->includes( $request ),
                    tenant: Tenancy::value(),
                ) );
            } catch( \Throwable $e ) {
                error_log( 'CMS watch middleware error: ' . $e->getMessage() );
            }
        }

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
     * Counts the resources in the JSON:API response body (best effort).
     */
    protected function count( Response $response ) : int
    {
        $content = $response->getContent();

        if( !is_string( $content ) || $content === '' ) {
            return 0;
        }

        $json = json_decode( $content, true );

        if( !is_array( $json ) || !array_key_exists( 'data', $json ) ) {
            return 0;
        }

        $data = $json['data'];

        if( is_array( $data ) ) {
            return array_is_list( $data ) ? count( $data ) : 1;
        }

        return 0;
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
