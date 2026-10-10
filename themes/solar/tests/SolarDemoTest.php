<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Resource;
use Aimeos\Cms\Models\Page;
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

        require_once dirname( __DIR__ ) . '/database/seeders/SolarDemo.php';

        ( new SolarDemo( 'solar', 'solar' ) )->seed();
        Tenancy::$callback = fn() => 'solar';
        app()->forgetInstance( Tenancy::class );
    }


    public function testDemo() : void
    {
        $projects = Page::where( 'path', 'projects' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $projects->id ) );
        $this->assertSame( 6, Page::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'solar', Page::where( 'tag', 'root' )->firstOrFail()->theme );
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
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'solar::business'}->data->{'call-button'} = false;
        $home->config = $config;
        Resource::updatePage( $home );

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\SolarServiceProvider',
        ] );
    }
}
