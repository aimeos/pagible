<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class SearchServiceProvider extends Provider
{
    public function register(): void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms.php', 'cms' );
    }


    public function boot(): void
    {
        $this->loadMigrationsFrom( dirname( __DIR__ ) . '/database/migrations' );

        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkSearch::class,
                \Aimeos\Cms\Commands\Index::class,
                \Aimeos\Cms\Commands\InstallSearch::class,
            ] );
        }

        app(\Laravel\Scout\EngineManager::class)->extend('cms', function () {
            return new \Aimeos\Cms\Scout\CmsEngine();
        });
    }
}
