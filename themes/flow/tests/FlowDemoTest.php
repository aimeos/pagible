<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Database\Seeders\FlowDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class FlowDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        ( new FlowDemo( 'flow', 'flow' ) )->seed();
        Tenancy::valueUsing( fn() => 'flow' );
    }


    public function testDemo() : void
    {
        $projects = PageView::where( 'path', 'projects' )->firstOrFail();
        $items = PageView::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $projects->id ) );
        $this->assertSame( 6, PageView::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'flow', PageView::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-flow', false );
        $response->assertSee( '"@type": "Plumber"', false );
        $response->assertSee( '"name": "Kirchzarten"', false );
        $response->assertSee( '"contactType": "emergency"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Friday"', false );
        $response->assertSee( 'class="emergency"', false );
        $response->assertSee( '24/7 emergency service' );
        $response->assertSee( 'href="tel:+497614587299"', false );
        $response->assertSee( 'class="call-button" href="tel:+497614587210"', false );
        $response->assertSee( 'Herdern heat pump' );
        $response->assertSee( 'Most booked' );
    }


    public function testProject() : void
    {
        $response = $this->get( '/herdern-heat-pump' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Before and after' );
        $response->assertSee( 'Step by step' );
    }


    public function testCallButtonDisabled() : void
    {
        $home = PageView::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'flow::business'}->data->{'call-button'} = false;
        $home->variant->update( ['config' => $config] );

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\FlowServiceProvider',
        ] );
    }
}
