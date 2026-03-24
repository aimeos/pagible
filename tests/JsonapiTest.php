<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\Testing\MakesJsonApiRequests;


class JsonapiTest extends TestAbstract
{
    use RefreshDatabase;
    use MakesJsonApiRequests;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.jsonapi_maxdepth', 2 );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'LaravelJsonApi\Laravel\ServiceProvider'
        ] );
    }


    public function testPages()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $pages = \Aimeos\Cms\Models\Page::where('tag', 'root')->get();

        $this->expectsDatabaseQueryCount( 5 ); // pages + page count + files + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        $response->assertFetchedMany( $pages );
        $this->assertGreaterThanOrEqual( 1, count( $pages ) );
    }


    public function testPagesFilter()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $pages = \Aimeos\Cms\Models\Page::where('tag', 'root')->get();

        $this->expectsDatabaseQueryCount( 5 ); // pages + page count + files + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )
            ->filter( ['domain' => 'mydomain.tld', 'path' => '', 'tag' => 'root'] )
            ->get( "cms/pages" );

        $response->assertFetchedMany( $pages );
    }


    public function testPage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 3 ); // page + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
        $response->assertJsonPath( 'meta.baseurl', '/storage/' );
    }


    public function testPageIncludeAncestors()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'article')->firstOrFail();
        $expected = [];

        foreach( $page->ancestors as $item ){
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 4 ); // page + ancestors + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'ancestors' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIncluded( $expected );
        $this->assertEquals( 2, count( $expected ) );
    }


    public function testPageIncludeChildren()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $page->children->filter( fn($item) => $item->status > 0 ) as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 4 ); // page + child pages + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'children' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIncluded( $expected );
        $this->assertGreaterThanOrEqual( 3, count( $expected ) );
    }


    public function testPageIncludeChildrenChildren()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $page->children->filter( fn($item) => $item->status > 0 ) as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 5 ); // page + children + children.children + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'children.children' )->get( "cms/pages/{$page->id}" );

        $response->assertStatus( 200 );
    }


    public function testPageIncludeMenu()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $page->menu as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 5 ); // page + ancestors + menu + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'menu' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIncluded( $expected );
        $this->assertEquals( 4, count( $expected ) );
    }


    public function testPageIncludeMenuChildren()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $page->menu as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 6 ); // page + ancestors + menu + children + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'menu,menu.children' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIncluded( $expected );
        $this->assertEquals( 4, count( $expected ) );
    }


    public function testPageFilterSubtree()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $pages = \Aimeos\Cms\Models\Page::where('tag', 'root')->get();
        $expected = [];

        foreach( $pages->first()->subtree as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 6 ); // page + count + files + elements + elements.files + page subtree
        $response = $this->jsonApi()->expects( 'pages' )
            ->filter( ['domain' => 'mydomain.tld', 'path' => '', 'tag' => 'root'] )
            ->includePaths( 'subtree' )->get( "cms/pages" );

        $response->assertFetchedMany( $pages )->assertIncluded( $expected );
        $this->assertEquals( 4, count( $expected ) );
    }


    public function testPageIncludeSubtree()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();
        $expected = [];

        foreach( $page->subtree as $item ) {
            $expected[] = ['type' => 'navs', 'id' => $item->id];
        }

        $this->expectsDatabaseQueryCount( 4 ); // page + page subtree + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'subtree' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIncluded( $expected );
        $this->assertEquals( 4, count( $expected ) );
    }


    public function testPageIncludeParent()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'article')->firstOrFail();
        $expected = $page->parent;

        $this->expectsDatabaseQueryCount( 4 ); // page + parent page + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'parent' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIsIncluded( 'navs', $expected );
    }


    public function testPageDisabled()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'disabled')->firstOrFail();

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertNotFound();
    }


    public function testPageDisabledParent()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'disabled-child')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 ); // page + parent
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'parent' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertDoesntHaveIncluded();
    }


    public function testPageHidden()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $page = \Aimeos\Cms\Models\Page::where('tag', 'hidden')->firstOrFail();

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
    }
}
