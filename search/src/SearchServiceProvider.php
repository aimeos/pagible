<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class SearchServiceProvider extends Provider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom( dirname( __DIR__ ) . '/database/migrations' );

        $this->console();
        $this->scout();
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkSearch::class,
                \Aimeos\Cms\Commands\Index::class,
                \Aimeos\Cms\Commands\InstallSearch::class,
            ] );
        }
    }

    protected function scout(): void
    {
        app(\Laravel\Scout\EngineManager::class)->extend('cms', function () {
            return new \Aimeos\Cms\Scout\CmsEngine();
        });

        // handle split content/draft search
        \Laravel\Scout\Builder::macro('searchFields', function( string ...$fields ) {
            return match( config('scout.driver') ) {
                'meilisearch' => $this->options( ['attributesToSearchOn' => $fields] ),
                'typesense' => $this->options( ['query_by' => implode( ',', $fields )] ),
                'algolia' => $this->options( ['restrictSearchableAttributes' => $fields] ),
                'cms' => $this->where( 'latest', in_array( 'draft', $fields ) ),
                default => $this,
            };
        });
    }
}
