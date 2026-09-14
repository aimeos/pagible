<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Commands\InstallWebhooks;
use Aimeos\Cms\Commands\PurgeWebhooks;
use Aimeos\Cms\Commands\ReencryptWebhooks;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\WebhookChanged;
use Aimeos\Cms\GraphQL\Directives\CmsPermissionDirective;
use Aimeos\Cms\Listeners\WebhookListener;
use Aimeos\Cms\Listeners\WebhookLogListener;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as Provider;
use Nuwave\Lighthouse\Events\BuildSchemaString;


class WebhookServiceProvider extends Provider
{
    public function boot() : void
    {
        $basedir = dirname( __DIR__ );

        Permission::register( 'config:webhook' );
        $this->loadMigrationsFrom( $basedir . '/database/migrations' );
        $this->publishes( [
            $basedir . '/config/cms/webhooks.php' => config_path( 'cms/webhooks.php' ),
        ], 'cms-config' );
        $this->publishes( [
            $basedir . '/admin/dist' => public_path( 'vendor/cms/webhooks' ),
        ], 'cms-admin' );

        if( class_exists( CmsPermissionDirective::class ) && class_exists( BuildSchemaString::class ) ) {
            Event::listen(
                BuildSchemaString::class,
                fn() => file_get_contents( $basedir . '/graphql/cms-webhook.graphql' ) ?: '',
            );
        }
        Event::listen( WebhookChanged::class, [WebhookLogListener::class, 'handle'] );

        if( (bool) config( 'cms.webhooks.enabled', false ) ) {
            $this->app->make( WebhookClient::class )->validatePolicy();
            $this->validateQueue();
            $this->events();
        }

        if( class_exists( Plugin::class ) ) {
            $panel = Plugin::all()['panels']['webhooks'] ?? null;

            if( $panel !== null && ( $panel['component'] ?? null ) !== '/vendor/cms/webhooks/WebhookList.js' ) {
                throw new \LogicException( 'The webhooks admin panel key is already registered.' );
            }

            if( $panel === null ) {
                Plugin::register( 'webhooks', [
                    'label' => 'Webhooks',
                    'permission' => 'config:webhook',
                    'component' => '/vendor/cms/webhooks/WebhookList.js',
                ] );
            }
        }

        if( $this->app->runningInConsole() ) {
            $this->commands( [InstallWebhooks::class, PurgeWebhooks::class, ReencryptWebhooks::class] );
        }
    }


    public function register() : void
    {
        $this->mergeConfigFrom( dirname( __DIR__ ) . '/config/cms/webhooks.php', 'cms.webhooks' );
        $this->app->singleton( WebhookClient::class );
        $this->app->singleton( WebhookManager::class );
    }


    protected function events() : void
    {
        foreach( [Published::class, Moved::class, Dropped::class, Restored::class, Purged::class, Bulk::class] as $event ) {
            Event::listen( $event, [WebhookListener::class, 'handle'] );
        }
    }


    protected function validateQueue() : void
    {
        if( !$this->app->bound( Encrypter::class )
            || !( $this->app->make( Encrypter::class ) instanceof Encrypter )
        ) {
            throw new \LogicException( 'CMS webhooks require Laravel queue encryption support.' );
        }

        $connection = config( 'cms.webhooks.queue.connection' ) ?: config( 'queue.default' );
        $driver = is_string( $connection ) ? config( "queue.connections.{$connection}.driver" ) : null;

        if( !is_string( $connection ) || !is_string( $driver ) || in_array( $driver, ['null', 'sync'], true ) ) {
            throw new \LogicException( 'CMS webhooks require an asynchronous queue connection.' );
        }
    }
}
