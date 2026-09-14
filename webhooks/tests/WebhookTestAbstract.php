<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Webhook;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


abstract class WebhookTestAbstract extends \Orchestra\Testbench\TestCase
{
    use InteractsWithViews;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    private static bool $cmsPrepared = false;

    protected ?\App\Models\User $user = null;
    protected $enablesPackageDiscoveries = true;


    protected function defineDatabaseMigrations()
    {
        // Persistent databases share Laravel's migration state between package suites.
        // Reset it once so the webhook migrations are applied by the root test suite.
        if( !self::$cmsPrepared ) {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            self::$cmsPrepared = true;
        }

        \Orchestra\Testbench\after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(\Orchestra\Testbench\default_migration_path());
        });
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver' => env( 'DB_DRIVER', 'sqlite' ),
            'host' => env( 'DB_HOST', '' ),
            'port' => env( 'DB_PORT', '' ),
            'database' => env( 'DB_DRIVER', 'sqlite' ) === 'sqlite' ? ':memory:' : env( 'DB_DATABASE', '' ),
            'username' => env( 'DB_USERNAME', '' ),
            'password' => env( 'DB_PASSWORD', '' ),
            'foreign_key_constraints' => true,
        ] );
        $app['config']->set( 'auth.providers.users.model', 'App\\Models\\User' );
        $app['config']->set( 'cache.default', 'array' );
        $app['config']->set( 'queue.default', 'database' );
        $app['config']->set( 'cms.db', 'testing' );
        $app['config']->set( 'cms.webhooks.enabled', true );
        $provider = new \ReflectionClass( \Aimeos\Cms\GraphqlServiceProvider::class );
        $schema = sys_get_temp_dir() . '/cms-webhooks-' . getmypid() . '.graphql';
        $base = file_get_contents( __DIR__ . '/default-schema.graphql' );
        $cms = file_get_contents( dirname( (string) $provider->getFileName(), 2 ) . '/schema/cms.graphql' );

        file_put_contents( $schema, (string) $base . PHP_EOL . (string) $cms );
        $app['config']->set( 'lighthouse.schema_path', $schema );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );

        \Aimeos\Cms\Tenancy::$callback = fn() => 'test';
    }


    protected function getPackageProviders( $app )
    {
        return [
            \Aimeos\Cms\CoreServiceProvider::class,
            \Aimeos\Nestedset\NestedSetServiceProvider::class,
            \Aimeos\Cms\GraphqlServiceProvider::class,
            \Aimeos\Cms\WebhookServiceProvider::class,
            \Nuwave\Lighthouse\LighthouseServiceProvider::class,
        ];
    }


    protected function setUp() : void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        $this->user = new \App\Models\User( [
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] );
    }


    protected function tearDown() : void
    {
        \Aimeos\Cms\Permission::canUsing( null );
        \Aimeos\Cms\Tenancy::$access = null;
        \Aimeos\Cms\Tenancy::$callback = null;
        parent::tearDown();
    }


    /**
     * @param array<string, mixed> $attributes
     */
    protected function webhook( array $attributes = [] ) : Webhook
    {
        $webhook = new Webhook();
        $webhook->forceFill( $attributes + [
            'tenant_id' => 'test',
            'status' => true,
            'revision' => 1,
            'failures' => 0,
            'url' => 'https://example.com/hooks/cms',
            'secret' => 'test-secret',
            'events' => ['page.published'],
            'last_error' => null,
            'editor' => 'editor@testbench',
        ] );
        $webhook->save();

        return $webhook;
    }
}
