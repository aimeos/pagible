<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Listeners\CdnListener;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as Provider;


class CdnServiceProvider extends Provider
{
    public function boot() : void
    {
        $this->publishes( [
            dirname( __DIR__ ) . '/config/cms/cdn.php' => config_path( 'cms/cdn.php' ),
        ], 'cms-config' );

        Event::listen( PageInvalidated::class, [CdnListener::class, 'pages'] );
        Event::listen( FilesRemoved::class, [CdnListener::class, 'files'] );
        Event::listen( RequestHandled::class, [CdnListener::class, 'headers'] );

        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\PurgeCdn::class,
            ] );
        }
    }


    public function register() : void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/cdn.php', 'cms.cdn' );
    }
}
