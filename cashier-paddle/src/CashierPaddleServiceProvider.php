<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Http\Middleware\CashierWebhook;
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

        CashierWebhook::register( 'cashier.webhook_secret', WebhookReceived::class,
            fn( WebhookReceived $event ) => app( CashierPaddle::class )->webhook( $event->payload )
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
