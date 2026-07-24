<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Events\CmsJsonapi;
use Aimeos\Cms\Listeners\JsonapiLogListener;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Scopes\Status;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;

class JsonapiServiceProvider extends Provider
{
    public function boot(): void
    {
        foreach( [Page::class, Nav::class] as $model )
        {
            $model::addGlobalScope( 'jsonapi', static function( $query ) {
                $request = request();

                if( !$request->attributes->get( 'cms.jsonapi' ) ) {
                    return;
                }

                ( new Status() )->apply( $query, $query->getModel() );
                $query->access( $request->user() );
            } );
        }

        $this->loadRoutesFrom( dirname( __DIR__ ) . '/routes/jsonapi.php' );
        $this->rateLimiter();

        $this->publishes( [dirname( __DIR__ ) . '/config/cms/jsonapi.php' => config_path( 'cms/jsonapi.php' )], 'cms-config' );

        $this->watch();
        $this->console();
    }


    protected function watch() : void
    {
        Watch::listen( [
            CmsJsonapi::class => JsonapiLogListener::class,
        ], 'cms.jsonapi.watch' );
    }


    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkJsonapi::class,
                \Aimeos\Cms\Commands\InstallJsonapi::class,
            ] );
        }
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/jsonapi.php', 'cms.jsonapi' );

        config(['jsonapi.servers' => array_merge(
            config('jsonapi.servers', []) ,
            ['cms' => \Aimeos\Cms\JsonApi\V1\Server::class]),
        ]);
    }


    protected function rateLimiter(): void
    {
        RateLimiter::for( 'cms-jsonapi', fn( $request ) =>
            Limit::perMinute( 60 )->by( $request->ip() )
        );
    }
}
