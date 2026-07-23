<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Schema;


class GraphqlSchemaTest extends GraphqlTestAbstract
{
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
