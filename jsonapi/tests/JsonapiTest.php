<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Concerns\ResolvesFiles;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Schema;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use LaravelJsonApi\Testing\MakesJsonApiRequests;


class JsonapiTest extends JsonapiTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesJsonApiRequests;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();
        Access::using( fn() => ['denied', 'member'] );
    }


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.jsonapi.maxdepth', 2 );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'LaravelJsonApi\Laravel\ServiceProvider'
        ] );
    }


    public function testJsonapiRateLimiter()
    {
        $this->assertNotNull( RateLimiter::limiter( 'cms-jsonapi' ) );
    }


    public function testPages()
    {

        $pages = \Aimeos\Cms\Models\Page::where('tag', 'root')->get();

        $this->expectsDatabaseQueryCount( 5 ); // pages + page count + files + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->get( 'cms/pages' );

        $response->assertFetchedMany( $pages );
        $this->assertGreaterThanOrEqual( 1, count( $pages ) );
    }


    public function testPagesFilter()
    {

        $pages = \Aimeos\Cms\Models\Page::where('tag', 'root')->get();

        $this->expectsDatabaseQueryCount( 5 ); // pages + page count + files + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )
            ->filter( ['domain' => 'mydomain.tld', 'path' => '', 'tag' => 'root'] )
            ->get( "cms/pages" );

        $response->assertFetchedMany( $pages );
    }


    public function testPage()
    {

        $page = \Aimeos\Cms\Models\Page::where('tag', 'root')->firstOrFail();

        $this->expectsDatabaseQueryCount( 3 ); // page + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
        $response->assertJsonPath( 'meta.baseurl', '/storage/' );
    }


    public function testPrivateFileUsesCoreAssetRoute()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $file = File::forceCreate( [
            'disk' => 'private',
            'mime' => 'image/svg+xml',
            'name' => 'private.svg',
            'path' => 'cms/test/private.svg',
            'previews' => [500 => 'cms/test/private-500.svg'],
            'editor' => 'test',
        ] );
        $items = [(object) ['files' => [$file->id]]];
        $result = $this->resolveFiles( $page, $items, collect( [$file->id => $file] ) );
        $asset = $result[0]->files[$file->id];

        $this->assertStringEndsWith( "/cmsasset/{$page->id}/{$file->id}", $asset->path );
        $this->assertStringEndsWith(
            "/cmsasset/{$page->id}/{$file->id}/500",
            ( (array) $asset->previews )[500],
        );
    }


    public function testSchemaActionOverridesStoredCallable()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $stored = false;
        $trusted = null;
        $action = function( Page $page ) use ( &$trusted ) {
            $trusted = $page->id;
            return 'trusted';
        };

        Schema::source( fn() => ['test' => ['content' => ['action' => ['fields' => [
            'action' => [
                'type' => 'hidden',
                'value' => $action,
            ],
        ]]]]] );

        $items = [(object) [
            'type' => 'test::action',
            'data' => (object) ['action' => function() use ( &$stored ) {
                $stored = true;
                return 'stored';
            }],
        ]];
        $result = $this->resolveFiles( $page, $items );

        $this->assertFalse( $stored );
        $this->assertSame( $page->id, $trusted );
        $this->assertSame( 'trusted', $result[0]->data->action );
    }


    public function testStoredCallableWithoutSchemaActionIsIgnored()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $stored = false;
        $items = [(object) [
            'type' => 'unknown',
            'data' => (object) ['action' => function() use ( &$stored ) {
                $stored = true;
                return 'stored';
            }],
        ]];
        $result = $this->resolveFiles( $page, $items );

        $this->assertFalse( $stored );
        $this->assertObjectNotHasProperty( 'action', $result[0]->data );
    }


    public function testPageIncludeAncestors()
    {

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

        $page = \Aimeos\Cms\Models\Page::where('tag', 'article')->firstOrFail();
        $expected = $page->parent;

        $this->expectsDatabaseQueryCount( 4 ); // page + parent page + elements + elements.files
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'parent' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertIsIncluded( 'navs', $expected );
    }


    public function testPageDisabled()
    {

        $page = \Aimeos\Cms\Models\Page::where('tag', 'disabled')->firstOrFail();

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertNotFound();
    }


    public function testPageDisabledParent()
    {

        $page = \Aimeos\Cms\Models\Page::where('tag', 'disabled-child')->firstOrFail();

        $this->expectsDatabaseQueryCount( 2 ); // page + parent
        $response = $this->jsonApi()->expects( 'pages' )->includePaths( 'parent' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page )->assertDoesntHaveIncluded();
    }


    public function testPageHidden()
    {

        $page = \Aimeos\Cms\Models\Page::where('tag', 'hidden')->firstOrFail();

        $this->expectsDatabaseQueryCount( 1 );
        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
    }


    public function testRestrictedPageIsHiddenFromGuests()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        PageAccess::set( [$page->id], [] );

        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertNotFound();
    }


    public function testEditorFromAnotherTenantCannotReadUnpublishedPage()
    {
        $page = Page::where( 'status', 0 )->firstOrFail();
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        $user->id = 42;
        $user->tenant_id = 'other';
        $this->actingAs( $user );

        $this->jsonApi()->expects( 'pages' )
            ->get( "cms/pages/{$page->id}" )
            ->assertNotFound();
    }


    public function testAuthenticationOnlyPageIsVisibleToAuthenticatedUsers()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        PageAccess::set( [$page->id], [] );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $this->actingAs( $user );

        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
    }


    public function testAnyGrantedPermissionMakesPageVisible()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        PageAccess::set( [$page->id], ['denied', 'member'] );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $this->actingAs( $user );
        Gate::define( 'denied', fn() => false );
        Gate::define( 'member', fn( $actualUser ) => $actualUser === $user );

        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$page->id}" );
        $response->assertFetchedOne( $page );
    }


    public function testDeniedPageIsHiddenFromAuthenticatedCollections()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        PageAccess::set( [$page->id], ['denied'] );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $this->actingAs( $user );
        Gate::define( 'denied', fn() => false );

        $response = $this->jsonApi()->expects( 'pages' )
            ->filter( ['tag' => 'root'] )
            ->get( 'cms/pages' );

        $response->assertSuccessful();
        $this->assertNotContains( $page->id, collect( $response->json( 'data', [] ) )->pluck( 'id' ) );
    }


    public function testAccessScopeIsLimitedToTheJsonapiRequest()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $allowed = new \App\Models\User();
        $allowed->id = 42;
        $allowed->tenant_id = 'test';
        $denied = new \App\Models\User();
        $denied->id = 43;
        $denied->tenant_id = 'test';
        $this->actingAs( $allowed );
        Gate::define( 'member', fn( $user ) => $user === $allowed );

        $this->jsonApi()->expects( 'pages' )
            ->get( "cms/pages/{$page->id}" )
            ->assertFetchedOne( $page );

        $this->actingAs( $denied );
        $this->assertNotNull( Page::find( $page->id ) );

        $this->jsonApi()->expects( 'pages' )
            ->get( "cms/pages/{$page->id}" )
            ->assertNotFound();
    }


    public function testRestrictedRelationshipsAreHiddenFromGuests()
    {
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $child = $page->children()->where( 'status', '>', 0 )->firstOrFail();
        PageAccess::set( [$child->id], [] );

        $response = $this->jsonApi()->expects( 'pages' )
            ->includePaths( 'children' )
            ->get( "cms/pages/{$page->id}" );

        $response->assertFetchedOne( $page );
        $this->assertNotContains( $child->id, collect( $response->json( 'included', [] ) )->pluck( 'id' ) );
    }


    /**
     * @param array<int|string, object> $items
     * @return array<int|string, object>
     */
    private function resolveFiles( Page $page, array $items, ?\Illuminate\Support\Collection $files = null ): array
    {
        $resolver = new class {
            use ResolvesFiles;

            public function resolve( Page $page, array $items, ?\Illuminate\Support\Collection $files ): array
            {
                return (array) $this->resolveFiles( $page, $items, $files );
            }
        };

        return $resolver->resolve( $page, $items, $files );
    }
}
