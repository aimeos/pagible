<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlQueryTest extends TestAbstract
{
    use DatabaseTruncation;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    protected $connectionsToTruncate = ['testing'];


    public function beginDatabaseTransaction()
    {
        // Prevent RefreshDatabase from wrapping tests in a transaction
        // MySQL/MariaDB/SQL Server FULLTEXT indexes can't see uncommitted data
    }


	protected function defineEnvironment( $app )
	{
        parent::defineEnvironment( $app );

		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
		$app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Nuwave\Lighthouse\LighthouseServiceProvider'
		] );
	}


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        $this->user = \App\Models\User::create([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmseditor' => PHP_INT_MAX,
        ]);
    }


    public function testPages()
    {
        $this->seed( CmsSeeder::class );
        sleep( 5 );

        $page = Page::where( 'tag', 'root' )->firstOrFail();

        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {
                any: "Home"
            }, first: 10, page: 1, publish: PUBLISHED) {
                data {
                    id
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' );

        $pagesData = $response->json( 'data.pages.data' );
        $this->assertCount( 1, $pagesData );
        $this->assertEquals( $page->id, $pagesData[0]['id'] );
    }


    public function testElements()
    {
        $this->seed( CmsSeeder::class );
        sleep( 5 );

        $element = Element::where( 'type', 'footer' )->firstOrFail();

        $response = $this->actingAs( $this->user )->graphQL( '{
            elements(filter: {
                any: "footer"
            }, first: 10, publish: DRAFT) {
                data {
                    id
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' );

        $elementsData = $response->json( 'data.elements.data' );
        $this->assertCount( 1, $elementsData );
        $this->assertEquals( $element->id, $elementsData[0]['id'] );
    }


    public function testFiles()
    {
        $this->seed( CmsSeeder::class );
        sleep( 5 );

        $response = $this->actingAs( $this->user )->graphQL( '{
            files(filter: {
                any: "image"
            }, first: 10) {
                data {
                    id
                }
                paginatorInfo {
                    currentPage
                    lastPage
                }
            }
        }' );

        $filesData = $response->json( 'data.files.data' );
        $this->assertGreaterThanOrEqual( 1, count( $filesData ) );
    }
}
