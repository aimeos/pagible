<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Listeners\AuthLogListener;
use Illuminate\Support\ServiceProvider as Provider;

class GraphqlServiceProvider extends Provider
{
    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->publishes( [$basedir . '/schema/cms.graphql' => base_path( 'graphql/cms.graphql' )], 'cms-graphql' );
        $this->publishes( [$basedir . '/config/cms/graphql.php' => config_path( 'cms/graphql.php' )], 'cms-config' );

        \Aimeos\Cms\Permission::register( [
            'page:metrics',
        ] );

        $this->app->make('events')->listen(
            \Nuwave\Lighthouse\Events\RegisterDirectiveNamespaces::class,
            fn() => 'Aimeos\\Cms\\GraphQL\\Directives'
        );

        $this->watch();
        $this->console();
    }

    public function register()
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/graphql.php', 'cms.graphql' );

        // Lighthouse ships query depth/complexity limits disabled. Enable sane
        // defaults to protect against deeply nested (e.g. recursive nav) or
        // expensive queries, unless the host application configured its own.
        if( !config( 'lighthouse.security.max_query_depth' ) ) {
            config( ['lighthouse.security.max_query_depth' => (int) config( 'cms.graphql.maxdepth', 15 )] );
        }

        if( !config( 'lighthouse.security.max_query_complexity' ) ) {
            config( ['lighthouse.security.max_query_complexity' => (int) config( 'cms.graphql.maxcomplexity', 300 )] );
        }
    }

    protected function watch() : void
    {
        $events = $this->app->make( 'events' );

        // Tag content changes made through the GraphQL API as 'graphql' for the audit log;
        // set per execution so it stays correct in long-running (Octane) workers.
        $events->listen(
            \Nuwave\Lighthouse\Events\StartExecution::class,
            fn() => Utils::source( 'graphql' )
        );

        Watch::listen( [
            Authed::class => AuthLogListener::class,
        ] );
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
