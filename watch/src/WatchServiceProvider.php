<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;
use Monolog\Formatter\JsonFormatter;


/**
 * Provides the structured log channel, configuration and event listeners for CMS observability.
 *
 * Each package defines and dispatches its own events; this watch package owns all the log
 * listeners (in src/Listeners) and is the only place that consumes the "cms.watch" config.
 */
class WatchServiceProvider extends Provider
{
    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        $this->publishes( [
            $basedir . '/config/cms/watch.php' => config_path( 'cms/watch.php' ),
        ], 'cms-watch-config' );

        $this->channel();
        $this->listeners();
        $this->console();
    }


    public function register() : void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/watch.php', 'cms.watch' );
    }


    /**
     * Registers a daily JSON log channel for the CMS when one is enabled but not predefined.
     */
    protected function channel() : void
    {
        $channel = config( 'cms.watch.channel' );

        if( is_string( $channel ) && $channel !== '' && !config( "logging.channels.{$channel}" ) )
        {
            config( ["logging.channels.{$channel}" => [
                'driver' => 'daily',
                'path' => storage_path( 'logs/cms.log' ),
                'level' => 'info',
                'days' => 14,
                'formatter' => JsonFormatter::class,
                'replace_placeholders' => true,
            ]] );
        }
    }


    protected function console() : void
    {
        if( $this->app->runningInConsole() )
        {
            $this->commands( [
                \Aimeos\Cms\Commands\InstallWatch::class,
            ] );
        }
    }


    /**
     * Subscribes the log listeners to the events of every installed CMS package when logging
     * is enabled. The watch package is the only place that consumes "cms.watch.channel".
     */
    protected function listeners() : void
    {
        if( !config( 'cms.watch.channel' ) ) {
            return;
        }

        $events = $this->app->make( 'events' );

        // Core content events are always present (the watch package requires the core package).
        foreach( [
            \Aimeos\Cms\Events\Added::class,
            \Aimeos\Cms\Events\Saved::class,
            \Aimeos\Cms\Events\Published::class,
            \Aimeos\Cms\Events\Dropped::class,
            \Aimeos\Cms\Events\Restored::class,
            \Aimeos\Cms\Events\Purged::class,
            \Aimeos\Cms\Events\Moved::class,
        ] as $event ) {
            $events->listen( $event, [\Aimeos\Cms\Listeners\ContentLogListener::class, 'handle'] );
        }

        $events->listen(
            \Aimeos\Cms\Events\Bulk::class,
            [\Aimeos\Cms\Listeners\ContentLogListener::class, 'handleBulk']
        );

        // Events from the optional packages, referenced by name so watch needs no dependency
        // on them; only wired when the package that defines the event is installed.
        $optional = [
            'Aimeos\\Cms\\Events\\Authed' => \Aimeos\Cms\Listeners\AuthLogListener::class,
            'Aimeos\\Cms\\Events\\Generated' => \Aimeos\Cms\Listeners\AiLogListener::class,
            'Aimeos\\Cms\\Events\\Searched' => \Aimeos\Cms\Listeners\SearchLogListener::class,
            'Aimeos\\Cms\\Events\\Contacted' => \Aimeos\Cms\Listeners\ContactLogListener::class,
            'Aimeos\\Cms\\Events\\Queried' => \Aimeos\Cms\Listeners\JsonapiLogListener::class,
        ];

        foreach( $optional as $event => $listener ) {
            if( class_exists( $event ) ) {
                $events->listen( $event, [$listener, 'handle'] );
            }
        }
    }


}
