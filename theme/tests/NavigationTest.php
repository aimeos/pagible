<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Navigation;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class NavigationTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();
        Access::availableUsing( fn() => [] );
    }


    public function testPageLookupPreservesEloquentFind(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( $page->id, Nav::find( $page->id )?->id );
        $route = Nav::page( $page->path, $page->domain );
        $this->assertNotNull( $route );
        $this->assertFalse( $route->access_exists );

        PageAccess::restrict( [$page->id], null );
        $route = Nav::page( $page->path, $page->domain );

        $this->assertNotNull( $route );
        $this->assertSame( $page->id, $route->id );
        $this->assertTrue( $route->access_exists );
    }


    public function testGuestNavigationQueryExcludesRestrictedPages(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $blog = Page::where( 'path', 'blog' )->firstOrFail();
        PageAccess::restrict( [$blog->id], null );

        $nav = new Navigation( $page, null );

        $this->assertNotContains( $blog->id, $nav->items()->pluck( 'id' ) );
    }


    public function testAuthenticatedNavigationIncludesAuthenticationOnlyPages(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $blog = Page::where( 'path', 'blog' )->firstOrFail();
        PageAccess::restrict( [$blog->id], null );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';

        $nav = new Navigation( $page, $user );

        $this->assertContains( $blog->id, $nav->items()->pluck( 'id' ) );
    }


    public function testRestrictedParentDoesNotPromotePublicChildren(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $blog = Page::where( 'path', 'blog' )->firstOrFail();
        $article = Page::where( 'path', 'welcome-to-laravelcms' )->firstOrFail();
        PageAccess::restrict( [$blog->id], null );

        $ids = ( new Navigation( $page, null ) )->items()->pluck( 'id' );

        $this->assertNotContains( $blog->id, $ids );
        $this->assertNotContains( $article->id, $ids );
    }


    public function testMemoizesLazyCollections(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $nav = new Navigation( $page, null );

        $this->assertSame( $nav->ancestors(), $nav->ancestors() );
        $this->assertSame( $nav->items(), $nav->items() );
        $this->assertNotSame( $nav->items(), $nav->items( 1 ) );
    }


    public function testNavigationCollectionsUseEightQueries(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $nav = new Navigation( $page, null );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $ancestors = $nav->ancestors();
        $nav->items();

        $this->assertCount( 8, DB::getQueryLog() );
        $this->assertNotEmpty( $ancestors );
        $this->assertFalse( $page->relationLoaded( 'ancestors' ) );
    }
}
