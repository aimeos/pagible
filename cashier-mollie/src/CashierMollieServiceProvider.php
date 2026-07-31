<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Http\Middleware\MollieWebhook;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as Provider;
use Laravel\Cashier\CashierServiceProvider as MollieCashierServiceProvider;
use Laravel\Cashier\Events\SubscriptionCancelled;
use Laravel\Cashier\Events\SubscriptionResumed;
use Laravel\Cashier\Events\SubscriptionStarted;
use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Plan\Contracts\PlanRepository;


class CashierMollieServiceProvider extends Provider
{
    /**
     * Registers lifecycle listeners, current and previous key-bound webhook routes, migrations, and scheduled billing.
     */
    public function boot(): void
    {
        Event::listen( SubscriptionCancelled::class, fn( SubscriptionCancelled $event ) =>
            app( CashierMollie::class )->subscription( $event->subscription, true )
        );
        Event::listen( SubscriptionResumed::class, fn( SubscriptionResumed $event ) =>
            app( CashierMollie::class )->subscription( $event->subscription )
        );
        Event::listen( SubscriptionStarted::class, fn( SubscriptionStarted $event ) =>
            app( CashierMollie::class )->subscription( $event->subscription )
        );

        $this->app->booted( function() {
            $key = (string) config( 'app.key' );
            $previous = array_unique( array_filter(
                (array) config( 'app.previous_keys', [] ),
                fn( mixed $old ) => is_string( $old ) && $old !== '' && $old !== $key,
            ) );
            $token = hash_hmac( 'sha256', 'cms-cashier-mollie-webhook', $key );

            foreach( ['webhooks.mollie.default', 'webhooks.mollie.aftercare', 'webhooks.mollie.first_payment'] as $name ) {
                $route = Route::getRoutes()->getByName( $name );

                if( !$route ) {
                    continue;
                }

                $route->middleware( MollieWebhook::class );
                $action = $route->getAction( 'uses' );

                if( !is_string( $action ) && !is_array( $action ) && !$action instanceof \Closure ) {
                    continue;
                }

                $uri = (string) $route->uri();

                if( !str_ends_with( $uri, '/' . $token ) ) {
                    continue;
                }

                $uri = substr( $uri, 0, -strlen( $token ) );

                foreach( $previous as $old )
                {
                    $alias = $uri . hash_hmac( 'sha256', 'cms-cashier-mollie-webhook', $old );
                    Route::post( $alias, $action )->middleware( $route->middleware() );
                }
            }
        } );

        if( $this->app->runningInConsole() )
        {
            $this->migrations();
            $this->callAfterResolving( Schedule::class, fn( Schedule $schedule ) => $this->schedule( $schedule ) );
        }
    }


    /**
     * Registers Cashier Mollie and the Pagible provider adapters.
     */
    public function register(): void
    {
        $this->app->register( MollieCashierServiceProvider::class );
        $this->app->singleton( CashierMollie::class );
        $this->app->alias( CashierMollie::class, CashierProvider::class );
        $this->app->bind( GetMolliePayment::class, CashierMolliePayment::class );
        $this->app->bind( PlanRepository::class, CashierMolliePlan::class );
        $this->webhooks();
    }


    /**
     * Publishes provider migrations in dependency order.
     */
    private function migrations() : void
    {
        $time = time();
        $path = dirname( __DIR__ ) . '/database/migrations/';

        $this->publishesMigrations( [
            $path . '2026_07_26_000001_add_users_mollie.php' => database_path(
                date( 'Y_m_d_His', $time + 1 ) . '_add_users_mollie.php'
            ),
            $path . '2026_07_26_000002_optimize_cashier_access.php' => database_path(
                date( 'Y_m_d_His', $time + 2 ) . '_optimize_cashier_access.php'
            ),
        ], 'cashier-migrations' );
    }


    /**
     * Schedules Cashier Mollie's recurring-order processing.
     */
    private function schedule( Schedule $schedule ) : void
    {
        $schedule->command( 'cashier:run' )
            ->everyFiveMinutes()
            ->withoutOverlapping( 15 )
            ->onOneServer()
            ->runInBackground();

    }


    /**
     * Appends the application-bound token to a configured webhook URL.
     */
    private function webhook( string $url, string $token ) : string
    {
        $path = parse_url( $url, PHP_URL_PATH );

        if( is_string( $path ) && str_ends_with( rtrim( $path, '/' ), '/' . $token ) ) {
            return $url;
        }

        $pos = strcspn( $url, '?#' );

        return rtrim( substr( $url, 0, $pos ), '/' ) . '/' . $token . substr( $url, $pos );
    }


    /**
     * Configures application-bound Mollie webhook paths.
     *
     * @throws \RuntimeException If no application key is configured
     */
    private function webhooks() : void
    {
        $key = (string) config( 'app.key' );

        if( $key === '' ) {
            throw new \RuntimeException( 'APP_KEY is required for Mollie webhook paths.' );
        }

        $token = hash_hmac( 'sha256', 'cms-cashier-mollie-webhook', $key );
        $urls = [
            'cashier.aftercare_webhook_url' => 'webhooks/mollie/aftercare',
            'cashier.first_payment.webhook_url' => 'webhooks/mollie/first-payment',
            'cashier.webhook_url' => 'webhooks/mollie',
        ];

        foreach( $urls as $config => $default ) {
            config()->set( $config, $this->webhook( (string) config( $config, $default ), $token ) );
        }
    }
}
