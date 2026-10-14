<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\FolioDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class FolioDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        ( new FolioDemo( 'folio', 'folio' ) )->seed();
        Tenancy::$callback = fn() => 'folio';
        app()->forgetInstance( Tenancy::class );
    }


    public function testAbout() : void
    {
        $response = $this->get( '/about' );

        $response->assertOk();
        $response->assertSee( 'class="timeline"', false );
        $response->assertSee( 'Staff Engineer, Northwind Labs' );
        $response->assertSee( '<code>Laravel</code>', false );
    }


    public function testDemo() : void
    {
        $writing = Page::where( 'path', 'writing' )->firstOrFail();
        $posts = Page::where( 'parent_id', $writing->id )->get();

        $this->assertSame( 'folio', Page::where( 'tag', 'root' )->firstOrFail()->theme );
        $this->assertCount( 6, $posts );
        $this->assertSame( ['blog'], $posts->pluck( 'type' )->unique()->values()->all() );
        $this->assertSame( '2026-10-02', $posts->firstWhere( 'path', 'zero-downtime-migrations' )->created_at->toDateString() );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-folio', false );
        $response->assertSee( '"@type": "ProfilePage"', false );
        $response->assertSee( '"@type": "Person"', false );
        $response->assertSee( '"jobTitle": "Staff Engineer"', false );
        $response->assertSee( 'https://github.com/example', false );
        $response->assertSee( 'class="mode"', false );
        $response->assertSee( 'data-copy="hello@example.com"', false );
        $response->assertSee( 'Recent writing' );
        $response->assertSee( 'Zero-downtime migrations on a 400M-row table' );
        $response->assertSee( 'laravel-flagship' );
        $response->assertDontSee( 'Writing a tiny full-text search engine in PHP' );
    }


    public function testPages() : void
    {
        foreach( ['/writing', '/projects', '/about', '/uses', '/now', '/octane-in-production', '/queues-are-a-design-tool',
            '/sqlite-in-memory-tests', '/terminal-setup-2026', '/tiny-search-engine'] as $path
        ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/now' )->assertSee( 'laravel-flagship 3.0' );
        $this->get( '/projects' )->assertSee( '4.8M' );
        $this->get( '/uses' )->assertSee( 'Neovim' );
    }


    public function testPost() : void
    {
        $response = $this->get( '/zero-downtime-migrations' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( '"@type": "BlogPosting"', false );
        $response->assertSee( '"name":"Lena Hartmann"', false );
        $response->assertSee( 'min read' );
        $response->assertSee( 'On this page' );
        $response->assertSee( 'Expand, migrate, contract' );
        $response->assertSee( 'Written by Lena Hartmann' );
        $response->assertSee( 'class="pager"', false );
        $response->assertSee( 'What I learned running Octane in production for a year' );
        $response->assertSee( 'class="language-php"', false );
        $response->assertSee( 'class="callout tip"', false );
    }


    public function testWriting() : void
    {
        $response = $this->get( '/writing' );

        $response->assertOk();
        $response->assertSeeInOrder( ['<section class="year">', '<h3>2026</h3>'], false );
        $response->assertSeeInOrder( ['Zero-downtime migrations', 'Octane in production', 'tiny full-text search engine'] );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\FolioServiceProvider',
        ] );
    }
}
