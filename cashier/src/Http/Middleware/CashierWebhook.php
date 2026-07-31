<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;


/**
 * Disables provider webhooks until signature verification is configured.
 */
class CashierWebhook
{
    /**
     * Continues only when the provider's webhook-verification secret is configured.
     */
    public function handle( Request $request, Closure $next, string $config ) : mixed
    {
        if( trim( (string) config( $config ) ) === '' ) {
            abort( 503 );
        }

        return $next( $request );
    }
}
