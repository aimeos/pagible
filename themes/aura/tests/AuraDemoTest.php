<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Database\Seeders\AuraDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AuraDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        ( new AuraDemo( 'aura', 'aura' ) )->seed();
        Tenancy::valueUsing( fn() => 'aura' );
    }


    public function testAbout() : void
    {
        $response = $this->get( '/about' );

        $response->assertOk();
        $response->assertSee( 'class="timeline"', false );
        $response->assertSee( 'Design Engineer, Northwind Studio' );
        $response->assertSee( 'testimonial-item', false );
    }


    public function testDemo() : void
    {
        $writing = PageView::where( 'path', 'writing' )->firstOrFail();
        $posts = PageView::where( 'parent_id', $writing->id )->get();

        $this->assertSame( 'aura', PageView::where( 'tag', 'root' )->firstOrFail()->theme );
        $this->assertCount( 5, $posts );
        $this->assertSame( ['blog'], $posts->pluck( 'type' )->unique()->values()->all() );
        $this->assertSame( '2026-09-24', $posts->firstWhere( 'path', 'animating-gradients-with-property' )->created_at->toDateString() );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-aura', false );
        $response->assertSee( '"@type": "ProfilePage"', false );
        $response->assertSee( '"jobTitle": "Design Engineer"', false );
        $response->assertSee( 'class="status"', false );
        $response->assertSee( 'Booking projects for 2027' );
        $response->assertSee( 'data-copy="hello@example.com"', false );
        $response->assertSee( 'class="wordmark"', false );
        $response->assertSee( 'layout-badges marquee', false );
        $response->assertSee( 'class="card-item clone" inert', false );
        $response->assertSee( 'data-count="24" data-scale="10" data-prefix="" data-sep="." data-digits="1" data-suffix="M"', false );
        $response->assertSee( 'Halo design system' );
        $response->assertDontSee( 'Orbit motion library' );
        $response->assertSee( 'list-cards', false );
        $response->assertSee( 'Animating gradients with @property' );
        $response->assertDontSee( 'Prototyping motion in the browser' );
    }


    public function testPages() : void
    {
        foreach( ['/work', '/writing', '/about', '/spring-physics-for-ui', '/design-system-in-40-tokens',
            '/view-transitions-for-portfolios', '/prototyping-motion-in-the-browser'] as $path
        ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/work' )->assertSee( 'Nordlys' );
    }


    public function testPost() : void
    {
        $response = $this->get( '/animating-gradients-with-property' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( '"@type": "BlogPosting"', false );
        $response->assertSee( 'class="progress"', false );
        $response->assertSee( 'On this page' );
        $response->assertSee( 'Register the colors' );
        $response->assertSee( 'Written by Noa Lindqvist' );
        $response->assertSee( 'class="pager"', false );
        $response->assertSee( 'class="language-css"', false );
        $response->assertSee( 'class="callout tip"', false );
    }


    public function testWriting() : void
    {
        $response = $this->get( '/writing' );

        $response->assertOk();
        $response->assertSee( 'list-rows', false );
        $response->assertSeeInOrder( ['Animating gradients', 'Spring physics', 'Prototyping motion'] );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\AuraServiceProvider',
        ] );
    }
}
