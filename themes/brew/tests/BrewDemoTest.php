<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\BrewDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class BrewDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/BrewDemo.php';

        ( new BrewDemo( 'brew', 'brew' ) )->seed();
        Tenancy::$callback = fn() => 'brew';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'brew::cafe'}->data->{'action-bar'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="action-bar"', false );
    }


    public function testDemo() : void
    {
        $journal = Page::where( 'path', 'journal' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $journal->id ) );
        $this->assertSame( 'brew', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-brew', false );
        $response->assertSee( '"@type": "CafeOrCoffeeShop"', false );
        $response->assertSee( '"@type": "OrderAction"', false );
        $response->assertSee( '"hasMenu": "http://localhost/menu"', false );
        $response->assertSee( '"servesCuisine": ["Coffee","Bakery","Breakfast"]', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Sunday"', false );
        $response->assertSee( 'Freshly roasted every Tuesday' );
        $response->assertSee( '<li class="order">', false );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+493412345670"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Ethiopia Guji Hambela' );
    }


    public function testMenu() : void
    {
        $response = $this->get( '/menu' );

        $response->assertOk();
        $response->assertSee( 'Flat white <strong>3.90</strong>', false );
        $response->assertSee( '<code>VG</code>', false );
    }


    public function testPages() : void
    {
        foreach( ['/coffee', '/classes', '/catering', '/wholesale', '/story', '/visit', '/journal', '/order', '/careers', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/story' )->assertSee( 'Lena Vogt' );
        $this->get( '/v60-guide' )->assertSee( 'Questions from our guests' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\BrewServiceProvider',
        ] );
    }
}
