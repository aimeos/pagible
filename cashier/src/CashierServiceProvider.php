<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;


class CashierServiceProvider extends Provider
{
    /**
     * Registers Cashier routes, migrations, throttling, commands, and access grants.
     */
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        RateLimiter::for( 'cms-cashier', function( $request ) {
            $limits = [Limit::perMinute( 10 )->by( 'ip:' . $request->ip() )];
            $user = $request->user();

            if( $user instanceof Authenticatable ) {
                $limits[] = Limit::perMinute( 10 )->by( 'user:' . $user->getAuthIdentifier() );
            }

            return $limits;
        } );

        $this->loadMigrationsFrom( $basedir . '/database/migrations' );
        $this->loadRoutesFrom( $basedir . '/routes/cashier.php' );

        if( $this->app->runningInConsole() ) {
            $this->commands( [
                \Aimeos\Cms\Commands\InstallCashier::class,
            ] );
        }

        Access::extend( fn( Authenticatable $user ) => app( CashierAccess::class )->roles( $user ) );
    }
}
