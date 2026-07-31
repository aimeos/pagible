<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Http\Middleware\CashierWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as Provider;
use Laravel\Paddle\Events\WebhookReceived;


class CashierPaddleServiceProvider extends Provider
{
    /**
     * Registers checkout views, verified webhook handling, and webhook guarding.
     */
    public function boot(): void
    {
        $this->loadViewsFrom( dirname( __DIR__ ) . '/resources/views', 'cms-cashier' );

        Event::listen( WebhookReceived::class, function( WebhookReceived $event ) {
            if( trim( (string) config( 'cashier.webhook_secret' ) ) !== '' ) {
                app( CashierPaddle::class )->webhook( $event->payload );
            }
        } );

        $this->app->booted( fn() => Route::getRoutes()->getByName( 'cashier.webhook' )
            ?->middleware( CashierWebhook::class . ':cashier.webhook_secret' )
        );

    }


    /**
     * Registers Paddle as the active Pagible Cashier provider.
     */
    public function register(): void
    {
        $this->app->singleton( CashierPaddle::class );
        $this->app->alias( CashierPaddle::class, CashierProvider::class );
    }
}
