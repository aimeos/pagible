<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TorqueDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class TorqueDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        ( new TorqueDemo( 'torque', 'torque' ) )->seed();
        Tenancy::valueUsing( fn() => 'torque' );
    }


    public function testActionBarDisabled() : void
    {
        $home = PageView::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'torque::garage'}->data->{'action-bar'} = false;
        $home->variant->update( ['config' => $config] );

        $this->get( '/' )->assertDontSee( 'class="action-bar"', false );
    }


    public function testDemo() : void
    {
        $tips = PageView::where( 'path', 'tips' )->firstOrFail();
        $items = PageView::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $tips->id ) );
        $this->assertSame( 'torque', PageView::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-torque', false );
        $response->assertSee( '"@type": "AutoRepair"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"@type": "OfferCatalog"', false );
        $response->assertSee( '"url": "http://localhost/prices"', false );
        $response->assertSee( '"priceRange": "€€"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Monday"', false );
        $response->assertSee( 'Winter tyre season: book your tyre change now' );
        $response->assertSee( 'class="breakdown" href="tel:+497118862199"', false );
        $response->assertSee( 'All repairs come with a 24 month warranty on parts and labour.' );
        $response->assertSee( '<li class="book">', false );
        $response->assertSee( 'class="phone"', false );
        $response->assertSee( 'Book service' );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+497118862140"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Jaguar I-PACE EV400' );
        $response->assertSee( '<code>48,000 km</code>', false );
    }


    public function testPages() : void
    {
        foreach( ['/services', '/servicing', '/brakes-tyres', '/diagnostics', '/electric-hybrid', '/prices', '/used-cars', '/offers', '/about', '/careers', '/tips', '/visit', '/book', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/about' )->assertSee( 'Deniz Yilmaz' );
        $this->get( '/used-cars' )->assertSee( 'Volvo V50 2.0' );
        $this->get( '/offers' )->assertSee( 'Instead of €99, code COOL79' );
        $this->get( '/winter-tyres' )->assertSee( 'Questions from our customers' );
    }


    public function testPrices() : void
    {
        $response = $this->get( '/prices' );

        $response->assertOk();
        $response->assertSee( 'Workshop prices' );
        $response->assertSee( 'Tyre change <code>30 min</code> <strong>€39</strong>', false );
    }


    public function testTips() : void
    {
        $response = $this->get( '/tips' );

        $response->assertOk();
        $response->assertSee( 'Dashboard Warning Lights: Stop Now or Drive On?' );
        $response->assertSee( '<a href="http://localhost/tips" class="active" aria-current="page">', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\TorqueServiceProvider',
        ] );
    }
}
