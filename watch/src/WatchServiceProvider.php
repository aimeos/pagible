<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\ServiceProvider as Provider;
use Monolog\Formatter\JsonFormatter;


/**
 * Registers the structured log channel for CMS observability.
 *
 * Each package defines, dispatches AND logs its own events: the log listeners live in the same
 * package as their events (e.g. ContentLogListener in core, AuthLogListener in graphql) and are
 * registered by that package's service provider. The "cms.watch" configuration lives in the core
 * "cms" config; this package only registers the daily JSON log channel when one is enabled.
 */
class WatchServiceProvider extends Provider
{
    public function boot() : void
    {
        $this->channel();
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
}
