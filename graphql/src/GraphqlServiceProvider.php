<?php

namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;

class GraphqlServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->publishes( [$basedir . '/schema/cms.graphql' => base_path( 'graphql/cms.graphql' )], 'cms-graphql-schema' );
        $this->publishes( [$basedir . '/config/cms/graphql.php' => config_path( 'cms/graphql.php' )], 'cms-graphql-config' );

        \Aimeos\Cms\Permission::register( [
            'page:metrics',
        ] );

        $this->app->make('events')->listen(
            \Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces::class,
            fn() => 'Aimeos\\Cms\\GraphQL\\Directives'
        );

        $this->console();
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/graphql.php', 'cms.graphql' );
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkGraphql::class,
                \Aimeos\Cms\Commands\InstallGraphql::class,
            ] );
        }
    }
}
