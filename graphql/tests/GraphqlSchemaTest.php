<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Aimeos\Cms\Schema;


class GraphqlSchemaTest extends GraphqlTestAbstract
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
			'name' => 'Test editor',
			'email' => 'editor@testbench',
			'password' => 'secret',
			'cmsperms' => \Aimeos\Cms\Permission::all(),
		]);
	}


	public function testSchemas()
	{
		$response = $this->actingAs( $this->user )->graphQL( '{
			schemas {
				name
				label
				types
				content
				meta
				config
			}
		}' );

		$response->assertJsonStructure( [
			'data' => [
				'schemas' => [
					'*' => ['name', 'label', 'types', 'content', 'meta', 'config'],
				],
			],
		] );

		$schemas = $response->json( 'data.schemas' );
		$this->assertNotEmpty( $schemas );

		$cms = collect( $schemas )->firstWhere( 'name', 'cms' );
		$this->assertNotNull( $cms );
		$this->assertEquals( 'Default', $cms['label'] );

		$types = is_string( $cms['types'] ) ? json_decode( $cms['types'], true ) : $cms['types'];
		$this->assertArrayHasKey( 'page', $types );
	}


	public function testSchemasUnauthenticated()
	{
		$response = $this->graphQL( '{
			schemas {
				name
			}
		}' );

		$response->assertGraphQLErrorMessage( 'Unauthenticated.' );
	}


	public function testSchemasContent()
	{
		$response = $this->actingAs( $this->user )->graphQL( '{
			schemas {
				name
				content
			}
		}' );

		$schemas = $response->json( 'data.schemas' );
		$cms = collect( $schemas )->firstWhere( 'name', 'cms' );

		$content = is_string( $cms['content'] ) ? json_decode( $cms['content'], true ) : $cms['content'];
		$this->assertArrayHasKey( 'heading', $content );
		$this->assertArrayHasKey( 'text', $content );
	}


	public function testSchemasNamespacing()
	{
		$path = sys_get_temp_dir() . '/cms-test-theme-ns';

		if( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		if( !is_dir( $path . '/views' ) ) {
			mkdir( $path . '/views', 0755, true );
		}

		file_put_contents( $path . '/schema.json', json_encode( [
			'label' => 'NS Test',
			'content' => [
				'custom-block' => ['group' => 'basic', 'fields' => ['title' => ['type' => 'string']]],
			],
		] ) );

		Schema::register( $path, 'nstest' );

		$response = $this->actingAs( $this->user )->graphQL( '{
			schemas {
				name
				content
			}
		}' );

		$schemas = $response->json( 'data.schemas' );
		$nstest = collect( $schemas )->firstWhere( 'name', 'nstest' );

		$this->assertNotNull( $nstest );

		$content = is_string( $nstest['content'] ) ? json_decode( $nstest['content'], true ) : $nstest['content'];
		$this->assertArrayHasKey( 'nstest::custom-block', $content );
		$this->assertArrayNotHasKey( 'custom-block', $content );
	}
}
