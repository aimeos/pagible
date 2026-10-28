<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


abstract class MetricsTestAbstract extends \Orchestra\Testbench\TestCase
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


    protected ?\App\Models\User $user = null;
    // the isolated package install (CI) doesn't discover the dependency providers, so all are listed below
    protected $enablesPackageDiscoveries = false;


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ] );
        $app['config']->set( 'auth.providers.users.model', 'App\\Models\\User' );
        $app['config']->set( 'cache.default', 'array' );
        $app['config']->set( 'cms.db', 'testing' );
        $app['config']->set( 'pulse.enabled', false );

        $provider = new \ReflectionClass( \Aimeos\Cms\GraphqlServiceProvider::class );
        $schema = sys_get_temp_dir() . '/cms-metrics-' . getmypid() . '.graphql';
        $base = file_get_contents( __DIR__ . '/default-schema.graphql' );
        $cms = file_get_contents( dirname( (string) $provider->getFileName(), 2 ) . '/schema/cms.graphql' );

        file_put_contents( $schema, (string) $base . PHP_EOL . (string) $cms );
        $app['config']->set( 'lighthouse.schema_path', $schema );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );

        \Aimeos\Cms\Tenancy::valueUsing( fn() => 'test' );
    }


    protected function getPackageProviders( $app )
    {
        return [
            \Aimeos\AnalyticsBridge\ServiceProvider::class,
            \Aimeos\KeywordsBridge\ServiceProvider::class,
            \Aimeos\Nestedset\NestedSetServiceProvider::class,
            \Laravel\Mcp\Server\McpServiceProvider::class,
            \Nuwave\Lighthouse\LighthouseServiceProvider::class,
            \Nuwave\Lighthouse\Auth\AuthServiceProvider::class,
            \Nuwave\Lighthouse\OrderBy\OrderByServiceProvider::class,
            \Nuwave\Lighthouse\Pagination\PaginationServiceProvider::class,
            \Nuwave\Lighthouse\SoftDeletes\SoftDeletesServiceProvider::class,
            \Nuwave\Lighthouse\Testing\TestingServiceProvider::class,
            \Nuwave\Lighthouse\Validation\ValidationServiceProvider::class,
            \Aimeos\Cms\CoreServiceProvider::class,
            \Aimeos\Cms\GraphqlServiceProvider::class,
            \Aimeos\Cms\McpServiceProvider::class,
            \Aimeos\Cms\MetricsServiceProvider::class,
        ];
    }
}
