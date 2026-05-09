<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlQueryTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    protected $connectionsToTruncate = ['testing'];


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
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

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ]);
    }


    public function testPages()
    {
        $this->seed( TestSeeder::class );

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
        $this->seed( TestSeeder::class );

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
        $this->seed( TestSeeder::class );

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
