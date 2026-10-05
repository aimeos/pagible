<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;


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


    /**
     * Handles verified provider webhooks and guards the "cashier.webhook" route once the routes are loaded.
     *
     * @param string $config Config key of the provider's webhook-verification secret
     * @param class-string $event Provider webhook event class
     * @param Closure $handler Receives the webhook event when the secret is configured
     */
    public static function register( string $config, string $event, Closure $handler ) : void
    {
        Event::listen( $event, function( object $event ) use ( $config, $handler ) {
            if( trim( (string) config( $config ) ) !== '' ) {
                $handler( $event );
            }
        } );

        app()->booted( fn() => Route::getRoutes()->getByName( 'cashier.webhook' )
            ?->middleware( self::class . ':' . $config )
        );
    }
}
