<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Http\Middleware;

use Aimeos\Cms\CashierMolliePayment;
use Closure;
use Illuminate\Http\Request;


/**
 * Validates untrusted Mollie webhook input before the upstream controller.
 */
class MollieWebhook
{
    /**
     * Continues only for a valid Mollie payment identifier.
     */
    public function handle( Request $request, Closure $next ) : mixed
    {
        CashierMolliePayment::id( $request->input( 'id' ) );

        return $next( $request );
    }
}
