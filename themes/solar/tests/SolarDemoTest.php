<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Database\Seeders\SolarDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SolarDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        ( new SolarDemo( 'solar', 'solar' ) )->seed();
        Tenancy::valueUsing( fn() => 'solar' );
    }


    public function testDemo() : void
    {
        $projects = PageView::where( 'path', 'projects' )->firstOrFail();
        $items = PageView::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $projects->id ) );
        $this->assertSame( 6, PageView::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'solar', PageView::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-solar', false );
        $response->assertSee( '"@type": "HomeAndConstructionBusiness"', false );
        $response->assertSee( '"name": "Kelheim"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Friday"', false );
        $response->assertDontSee( 'class="emergency"', false );
        $response->assertSee( 'class="call-button" href="tel:+499415893410"', false );
        $response->assertSee( 'Donaustauf solar and battery' );
        $response->assertSee( 'Most chosen' );
    }


    public function testProject() : void
    {
        $response = $this->get( '/kelheim-heat-pump' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Before and after' );
        $response->assertSee( 'Step by step' );
    }


    public function testCallButtonDisabled() : void
    {
        $home = PageView::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'solar::business'}->data->{'call-button'} = false;
        $home->variant->update( ['config' => $config] );

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\SolarServiceProvider',
        ] );
    }
}
