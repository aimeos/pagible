<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\ManeDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class ManeDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/ManeDemo.php';

        ( new ManeDemo( 'mane', 'mane' ) )->seed();
        Tenancy::$callback = fn() => 'mane';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'mane::salon'}->data->{'action-bar'} = false;
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
        $this->assertSame( 'mane', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-mane', false );
        $response->assertSee( '"@type": "HairSalon"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"@type": "OfferCatalog"', false );
        $response->assertSee( '"url": "http://localhost/services"', false );
        $response->assertSee( '"priceRange": "€€€"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Saturday"', false );
        $response->assertDontSee( 'servesCuisine', false );
        $response->assertSee( 'New clients get a free 15 minute consultation' );
        $response->assertSee( 'Please cancel or move your appointment at least 24 hours before.' );
        $response->assertSee( '<li class="book">', false );
        $response->assertSee( 'Book now' );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+498923456780"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Amara Okafor' );
    }


    public function testJournal() : void
    {
        $response = $this->get( '/journal' );

        $response->assertOk();
        $response->assertSee( 'class="list-item"', false );
        $response->assertSee( 'How to Care for Curly Hair' );
        $response->assertSee( '<a href="http://localhost/journal" class="active">', false );
    }


    public function testPages() : void
    {
        foreach( ['/services', '/colour', '/bridal', '/team', '/careers', '/new-clients', '/gift-cards', '/journal', '/visit', '/book', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/team' )->assertSee( 'Lena Hofmann' );
        $this->get( '/curl-care' )->assertSee( 'Questions from our clients' );
    }


    public function testServices() : void
    {
        $response = $this->get( '/services' );

        $response->assertOk();
        $response->assertSee( 'Prices by stylist level' );
        $response->assertSee( 'Balayage <code>bestseller</code> <strong>220</strong>', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\ManeServiceProvider',
        ] );
    }
}
