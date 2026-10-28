<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\InstallMetrics;
use Aimeos\Cms\Mcp\CmsServer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as Provider;
use Nuwave\Lighthouse\Events\BuildSchemaString;


class MetricsServiceProvider extends Provider
{
    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        Permission::register( 'page:metrics' );
        $this->publishes( [
            $basedir . '/admin/dist' => public_path( 'vendor/cms/metrics' ),
        ], 'cms-admin' );

        // The GraphQL package requires Lighthouse
        if( class_exists( GraphqlServiceProvider::class ) ) {
            Event::listen(
                BuildSchemaString::class,
                fn() => file_get_contents( $basedir . '/graphql/cms-metrics.graphql' ) ?: '',
            );
        }

        if( class_exists( CmsServer::class ) ) {
            CmsServer::register( [
                \Aimeos\Cms\Tools\GetPageMetrics::class,
                \Aimeos\Cms\Tools\GetKeywordVolume::class,
                \Aimeos\Cms\Tools\GetRelatedKeywords::class,
            ] );
        }

        if( class_exists( Plugin::class ) ) {
            Plugin::register( 'page:metrics', [
                'label' => 'Metrics',
                'i18n' => 'metrics',
                'permission' => 'page:metrics',
                'component' => '/vendor/cms/metrics/index.js',
            ] );
        }

        if( $this->app->runningInConsole() ) {
            $this->commands( [InstallMetrics::class] );
        }
    }
}
