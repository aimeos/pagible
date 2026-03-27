<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlMetricsTest extends GraphqlTestAbstract
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


	protected function defineEnvironment( $app )
	{
        parent::defineEnvironment( $app );

		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
		$app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        $this->user = new \App\Models\User([
            'name' => 'Admin',
            'email' => 'admin@testbench',
            'password' => 'secret',
        ]);
        $this->user->cmsperms = ['admin'];
    }



    public function testMetrics()
    {
        $expected = [
            'views' => [
                ['key' => '2025-08-01', 'value' => 10],
                ['key' => '2025-08-02', 'value' => 20],
            ],
            'visits' => [
                ['key' => '2025-08-01', 'value' => 5],
                ['key' => '2025-08-02', 'value' => 15],
            ],
            'durations' => [
                ['key' => '2025-08-01', 'value' => 60],
                ['key' => '2025-08-02', 'value' => 90],
            ],
            'countries' => [
                ['key' => 'Germany', 'value' => 12],
                ['key' => 'USA', 'value' => 8],
            ],
            'referrers' => [
                ['key' => 'google.com', 'value' => 6],
                ['key' => 'bing.com', 'value' => 3],
            ],
        ];

        $pagespeed = [
            ['key' => 'time_to_first_byte', 'value' => 250]
        ];

        Analytics::shouldReceive('driver->stats')
            ->once()
            ->with('/test', 30)
            ->andReturn($expected);

        Analytics::shouldReceive('pagespeed')
            ->once()
            ->with('/test')
            ->andReturn($pagespeed);


        $response = $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "/test", days: 30) {
                    errors
                    views { key value }
                    visits { key value }
                    durations { key value }
                    countries { key value }
                    referrers { key value }
                    pagespeed { key value }
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'metrics' => $expected + ['pagespeed' => $pagespeed],
            ],
        ]);
    }


    public function testMetricsEmptyUrl()
    {
        $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "", days: 30) {
                    views { key value }
                }
            }
        ')->assertGraphQLErrorMessage('URL must be a non-empty string');
    }


    public function testMetricsInvalidDays()
    {
        $this->actingAs($this->user)->graphQL('
            mutation {
                metrics(url: "/test", days: 100) {
                    views { key value }
                }
            }
        ')->assertGraphQLErrorMessage('Number of days must be an integer between 1 and 90, got "100"');
    }


    public function testMetricsNoPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No permission',
            'email' => 'noperm@testbench',
            'password' => 'secret',
        ]);
        $user->cmsperms = [];

        $this->actingAs( $user )->graphQL( '
            mutation {
                metrics(url: "/test", days: 30) {
                    views { key value }
                }
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }
}
