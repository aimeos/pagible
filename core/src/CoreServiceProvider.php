<?php

namespace Aimeos\Cms;

use Aimeos\Cms\Events\Added;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Listeners\BulkListener;
use Aimeos\Cms\Listeners\ContentListener;
use Aimeos\Cms\Models\Version;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
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
        ], 'cms-config' );

        Watch::registerChannel();
        $this->broadcast();
        $this->watch();
        $this->rateLimiter();
        $this->userCasts();
        $this->schedule();
        $this->console();
        $this->scout();
    }

    public function register()
    {
        $cfgdir = dirname( __DIR__ ) . '/config';
        $this->mergeConfigFrom( $cfgdir . '/cms.php', 'cms' );

        $this->app->scoped( \Aimeos\Cms\Tenancy::class, function() {
            $callback = \Aimeos\Cms\Tenancy::$callback;
            return new \Aimeos\Cms\Tenancy( $callback ? $callback() : '' );
        } );

        $this->app->scoped( \Aimeos\Cms\Access::class );
    }

    protected function broadcast() : void
    {
        if( !config( 'cms.broadcast' ) ) {
            return;
        }

        Broadcast::routes( ['middleware' => config( 'cms.broadcast-middleware', ['web', 'auth'] )] );

        foreach( ['page', 'element', 'file'] as $type )
        {
            // Single-tenant only: the tenant-less channel is authorized solely when no tenancy is
            // configured at all. Requiring Tenancy::$callback === null fails closed - in a
            // multi-tenant deployment whose /broadcasting/auth route lacks the tenancy-init
            // middleware, Tenancy::value() would be '' and would otherwise open this channel to
            // every tenant.
            Broadcast::channel( Channel::type( '', $type ), fn( $user ) =>
                Tenancy::value() === '' && Tenancy::$callback === null && Permission::can( "{$type}:view", $user )
            );

            // Multi-tenant: the channel's tenant segment must match the request's tenant; the
            // permission check also binds the user to the current tenant.
            Broadcast::channel( Channel::type( '{tenant}', $type ), fn( $user, string $tenant ) =>
                $tenant === Tenancy::value() && Permission::can( "{$type}:view", $user )
            );
        }
    }


    /**
     * Subscribes the content audit listener to the per-action events when watch logging is enabled.
     *
     * Gated on "cms.watch.channel" so nothing listens when logging is off, which keeps
     * Broadcasts::announce() short-circuiting (no event built, no latest version loaded).
     */
    protected function watch() : void
    {
        $listener = ContentListener::class;

        Watch::listen( [
            Added::class => $listener,
            Saved::class => $listener,
            Published::class => $listener,
            Dropped::class => $listener,
            Restored::class => $listener,
            Purged::class => $listener,
            Moved::class => $listener,
            Bulk::class => BulkListener::class,
        ] );
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


    protected function schedule() : void
    {
        $this->app->afterResolving( Schedule::class, function( Schedule $schedule ) {
            $schedule->command( 'cms:publish' )->everyThirtyMinutes()
                ->withoutOverlapping()->onOneServer();
            $schedule->command( 'model:prune', ['--model' => Version::TYPES] )->daily();
        } );
    }


    protected function rateLimiter(): void
    {
        RateLimiter::for( 'cms-broadcast', fn( $request ) =>
            Limit::perMinute( 120 )->by( $request->user()?->getAuthIdentifier() ?: $request->ip() )
        );
    }
}
