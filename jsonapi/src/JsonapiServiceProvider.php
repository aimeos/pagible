<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class JsonapiServiceProvider extends Provider
{
    public function boot(): void
    {
        $this->loadRoutesFrom( dirname( __DIR__ ) . '/routes/jsonapi.php' );

        $this->publishes( [dirname( __DIR__ ) . '/config/cms/jsonapi.php' => config_path( 'cms/jsonapi.php' )], 'cms-jsonapi-config' );

        $this->console();
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
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
}
