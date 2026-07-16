<?php

namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;
use Nuwave\Lighthouse\Events\BuildSchemaString;

class AdminServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->app->make( 'events' )->listen(
            BuildSchemaString::class,
            fn() => file_get_contents( $basedir . '/schema/admin.graphql' ) ?: ''
        );

        $this->loadViewsFrom( $basedir . '/views', 'cms' );
        $this->loadRoutesFrom( $basedir . '/routes/admin.php' );
        $this->rateLimiter();

        $this->publishes( [$basedir . '/dist' => public_path( 'vendor/cms/admin' )], 'cms-admin' );
        $this->publishes( [$basedir . '/config/cms/admin.php' => config_path( 'cms/admin.php' )], 'cms-config' );
    }


    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/admin.php', 'cms.admin' );
    }


    protected function rateLimiter(): void
    {
        RateLimiter::for( 'cms-proxy', fn( $request ) =>
            Limit::perMinute( 30 )->by( $request->ip() )
        );
    }
}
