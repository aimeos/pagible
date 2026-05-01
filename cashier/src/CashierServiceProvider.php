<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;


class CashierServiceProvider extends Provider
{
    /** @var array<string, array{string, string}> provider => [package, namespace] */
    public const PROVIDERS = [
        'stripe' => ['laravel/cashier', 'Laravel\Cashier'],
        'paddle' => ['laravel/cashier-paddle', 'Laravel\Paddle'],
        'mollie' => ['mollie/laravel-cashier-mollie', 'Laravel\CashierMollie'],
    ];

    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        RateLimiter::for( 'cms-cashier', fn( $request ) =>
            Limit::perMinute( 10 )->by( $request->ip() )
        );

        $this->loadRoutesFrom( $basedir . '/routes/cashier.php' );
        $this->publishes( [$basedir . '/config/cms/cashier.php' => config_path( 'cms/cashier.php' )], 'cms-config' );

        $this->console();
    }


    public function register(): void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/cashier.php', 'cms.cashier' );
    }


    protected function console(): void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\InstallCashier::class,
            ] );
        }
    }
}
