<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class GraphqlQueryTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


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
