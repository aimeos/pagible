<?php

namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider as Provider;

class CoreServiceProvider extends Provider
{
    protected bool $defer = false;

    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadMigrationsFrom( $basedir . '/database/migrations' );
        $this->publishes( [
            $basedir . '/config/cms.php' => config_path( 'cms.php' ),
            $basedir . '/config/cms/schemas.php' => config_path( 'cms/schemas.php' ),
        ], 'cms-core-config' );

        $this->rateLimiter();
        $this->userCasts();
        $this->console();

        // Set null Scout driver as fallback if none configured
        config(['scout.driver' => config('scout.driver', 'null')]);

        $this->scout();
    }

    public function register()
    {
        $cfgdir = dirname( __DIR__ ) . '/config';
        $this->mergeConfigFrom( $cfgdir . '/cms.php', 'cms' );

        // Load schemas from the published config/cms/schemas.php if present, else the package default.
        $path = config_path( 'cms/schemas.php' );
        $this->mergeConfigFrom( file_exists( $path ) ? $path : $cfgdir . '/cms/schemas.php', 'cms.schemas' );

        $this->app->scoped( \Aimeos\Cms\Tenancy::class, function() {
            $callback = \Aimeos\Cms\Tenancy::$callback;
            return new \Aimeos\Cms\Tenancy( $callback ? $callback() : '' );
        } );
    }

    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\BenchmarkCore::class,
                \Aimeos\Cms\Commands\InstallCore::class,
                \Aimeos\Cms\Commands\Publish::class,
                \Aimeos\Cms\Commands\User::class,
            ] );
        }
    }

    protected function scout() : void
    {
        if( !\Laravel\Scout\Builder::hasMacro( 'searchFields' ) )
        {
            \Laravel\Scout\Builder::macro( 'searchFields', function( string ...$fields ) {
                $this->where( 'tenant_id', Tenancy::value() );

                match( config( 'scout.driver' ) ) {
                    'collection' => $this->callback = fn( $query, $builder ) => Scout::collection( $query, $builder, $fields ),
                    'algolia' => $this->options( ['restrictSearchableAttributes' => $fields] ),
                    'typesense' => $this->options( ['query_by' => implode( ',', $fields )] ),
                    'meilisearch' => $this->options( ['attributesToSearchOn' => $fields] ),
                    'cms' => $this->where( 'latest', in_array( 'draft', $fields ) ),
                    default => null,
                };
                return $this;
            } );
        }
    }

    protected function userCasts() : void
    {
        $this->app->booted( function() {
            $userClass = config( 'auth.providers.users.model', 'App\\Models\\User' );

            if( !$userClass || !class_exists( $userClass ) || !method_exists( $userClass, 'mergeCasts' ) ) {
                return;
            }

            $casts = ['cmsperms' => 'array'];

            $userClass::retrieved( function( $model ) use ( $casts ) {
                $model->mergeCasts( $casts );
            } );

            $userClass::saving( function( $model ) use ( $casts ) {
                $model->mergeCasts( $casts );

                if( is_array( $model->getAttributes()['cmsperms'] ?? null ) ) {
                    $model->setAttribute( 'cmsperms', $model->getAttributes()['cmsperms'] );
                }
            } );
        } );
    }


    protected function rateLimiter(): void
    {
        RateLimiter::for( 'cms-admin', fn( $request ) =>
            Limit::perMinute( 120 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
        );

        RateLimiter::for( 'cms-ai', fn( $request ) =>
            Limit::perMinute( 10 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
        );

        RateLimiter::for( 'cms-contact', fn( $request ) =>
            Limit::perMinute( 2 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-jsonapi', fn( $request ) =>
            Limit::perMinute( 60 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-login', fn( $request ) =>
            Limit::perMinute( 10 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-proxy', fn( $request ) =>
            Limit::perMinute( 30 )->by( $request->ip() )
        );

        RateLimiter::for( 'cms-search', fn( $request ) =>
            Limit::perMinute( 60 )->by( $request->ip() )
        );
    }
}
