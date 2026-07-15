<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;


/**
 * Limits conditional JSON:API model scopes to downstream request processing.
 */
class ScopeJsonapi
{
    public function handle( Request $request, Closure $next ) : mixed
    {
        $request->attributes->set( 'cms.jsonapi', true );

        try {
            return $next( $request );
        } finally {
            $request->attributes->remove( 'cms.jsonapi' );
        }
    }
}
