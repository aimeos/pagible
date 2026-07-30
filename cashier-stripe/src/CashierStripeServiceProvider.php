<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Http\Middleware\CashierWebhook;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as Provider;
use Laravel\Cashier\Console\WebhookCommand;
use Laravel\Cashier\Events\WebhookReceived;


class CashierStripeServiceProvider extends Provider
{
    /**
     * Registers required events, verified webhook handling, and webhook guarding.
     */
    public function boot(): void
    {
        $events = (array) ( config( 'cashier.webhook.events' ) ?: WebhookCommand::DEFAULT_EVENTS );

        config( ['cashier.webhook.events' => array_values( array_unique( [
            ...$events,
            ...CashierStripe::EVENTS,
        ] ) )] );

        Event::listen( WebhookReceived::class, function( WebhookReceived $event ) {
            if( trim( (string) config( 'cashier.webhook.secret' ) ) !== '' ) {
                app( CashierStripe::class )->webhook( $event->payload );
            }
        } );

        $this->app->booted( fn() => Route::getRoutes()
            ->getByName( 'cashier.webhook' )
            ?->middleware( CashierWebhook::class . ':cashier.webhook.secret' )
        );

    }


    /**
     * Registers Stripe as the active Pagible Cashier provider.
     */
    public function register(): void
    {
        $this->app->singleton( CashierStripe::class );
        $this->app->alias( CashierStripe::class, CashierProvider::class );
    }
}
