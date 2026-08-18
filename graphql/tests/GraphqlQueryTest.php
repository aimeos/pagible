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


    public function testPagePathFilterUsesPublishedPageColumns()
    {
        $live = Page::where( 'path', 'blog' )->firstOrFail();
        $dev = Page::where( 'path', 'hidden' )->firstOrFail();

        $live->forceFill( ['path' => 'features', 'domain' => 'live.example'] )->saveQuietly();
        $dev->forceFill( ['path' => 'features', 'domain' => 'dev.example'] )->saveQuietly();

        $draft = $live->versions()->forceCreate( [
            'lang' => 'en',
            'data' => ['path' => 'features', 'domain' => 'dev.example'],
            'editor' => 'test',
        ] );
        $live->forceFill( ['latest_id' => $draft->id] )->saveQuietly();

        $response = $this->actingAs( $this->user )->graphQL( <<<'GRAPHQL'
            query($filter: PageFilter) {
                pages(filter: $filter) {
                    data {
                        id
                    }
                }
            }
            GRAPHQL, ['filter' => ['path' => 'features', 'domain' => 'dev.example']] );

        $response->assertJsonCount( 1, 'data.pages.data' );
        $this->assertSame( $dev->id, $response->json( 'data.pages.data.0.id' ) );
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
