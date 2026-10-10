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
use Aimeos\Cms\Events\PermissionChanged;
use Aimeos\Cms\Listeners\LogListener;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider as Provider;

class CoreServiceProvider extends Provider
{
    protected bool $defer = false;

    public function boot(): void
    {
        $basedir = dirname( __DIR__ );

        $this->loadMigrationsFrom( $basedir . '/database/migrations' );
        $this->loadRoutesFrom( $basedir . '/routes/core.php' );
        $this->publishes( [
            $basedir . '/config/cms.php' => config_path( 'cms.php' ),
        ], 'cms-config' );

        Watch::registerChannel();
        $this->broadcast();
        $this->watch();
        $this->flush();
        Utils::limit( 'cms-asset', 300 );
        Utils::limit( 'cms-broadcast', 120 );
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


    /**
     * Flushes Permission state after each queued job and each Octane request,
     * so long-running workers never honor stale in-memory permission caches.
     */
    protected function flush() : void
    {
        \Illuminate\Support\Facades\Queue::after( fn() => \Aimeos\Cms\Permission::flush() );
        \Illuminate\Support\Facades\Queue::failing( fn() => \Aimeos\Cms\Permission::flush() );

        if( class_exists( \Laravel\Octane\Events\RequestTerminated::class ) ) {
            \Illuminate\Support\Facades\Event::listen(
                \Laravel\Octane\Events\RequestTerminated::class,
                fn() => \Aimeos\Cms\Permission::flush(),
            );
        }
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
     * Subscribes the log listener to the per-action content events when watch logging is enabled.
     *
     * Gated on "cms.watch.channel" so nothing listens when logging is off, which keeps
     * Broadcasts::announce() short-circuiting (no event built, no latest version loaded).
     */
    protected function watch() : void
    {
        Watch::listen( [
            Added::class,
            Saved::class,
            Published::class,
            Dropped::class,
            Restored::class,
            Purged::class,
            Moved::class,
            Bulk::class,
        ] );

        // Permission grants are security-relevant and must always be audited, so this
        // listener is registered unconditionally — NOT through the watch-channel-gated
        // Watch::listen() above. Warnings fall back to the default log channel when
        // no cms.watch.channel is configured.
        \Illuminate\Support\Facades\Event::listen( PermissionChanged::class, [LogListener::class, 'handle'] );
    }


    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\InstallCore::class,
                \Aimeos\Cms\Commands\Previews::class,
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
            $schedule->command( 'model:prune', ['--model' => [...Version::TYPES, PageVariant::class]] )->daily();
        } );
    }
}
