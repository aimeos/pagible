<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\GroveDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class GroveDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/GroveDemo.php';

        ( new GroveDemo( 'grove', 'grove' ) )->seed();
        Tenancy::$callback = fn() => 'grove';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'grove::garden'}->data->{'action-bar'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="action-bar"', false );
    }


    public function testCarePlans() : void
    {
        $response = $this->get( '/care-plans' );

        $response->assertOk();
        $response->assertSee( 'Garden care plans' );
        $response->assertSee( 'Most popular' );
        $response->assertSee( 'Mowing and edging <code>every visit</code>', false );
    }


    public function testDemo() : void
    {
        $journal = Page::where( 'path', 'journal' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $journal->id ) );
        $this->assertSame( 'grove', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-grove', false );
        $response->assertSee( '"@type": "HomeAndConstructionBusiness"', false );
        $response->assertSee( '"@type": "QuoteAction"', false );
        $response->assertSee( '"@type": "OfferCatalog"', false );
        $response->assertSee( '"url": "http://localhost/care-plans"', false );
        $response->assertSee( '"name": "Godalming"', false );
        $response->assertSee( '"priceRange": "££"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Monday"', false );
        $response->assertSee( 'Autumn tidy-ups and bulb planting: book your visit now' );
        $response->assertSee( 'Serving Guildford · Godalming · Woking · Farnham' );
        $response->assertSee( 'Areas we cover' );
        $response->assertSee( 'Fully insured, BALI registered, and all our planting comes with a 12 month guarantee.' );
        $response->assertSee( '<li class="quote">', false );
        $response->assertSee( 'class="phone"', false );
        $response->assertSee( 'Free quote' );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'href="tel:+441632960418"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Walled kitchen garden' );
        $response->assertSee( '<code>Shalford · 2025</code>', false );
    }


    public function testJournal() : void
    {
        $response = $this->get( '/journal' );

        $response->assertOk();
        $response->assertSee( 'A Greener Lawn Without Chemicals: Our Year-Round Guide' );
        $response->assertSee( '<a href="http://localhost/journal" class="active">', false );
    }


    public function testPages() : void
    {
        foreach( ['/services', '/garden-design', '/landscaping', '/garden-maintenance', '/lawns-hedges', '/projects', '/care-plans', '/about', '/journal', '/contact', '/quote', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/about' )->assertSee( 'Geoff Marlow' );
        $this->get( '/projects' )->assertSee( 'Japanese gravel garden' );
        $this->get( '/lawns-hedges' )->assertSee( 'Hedge up to 2 m <code>per 10 metres</code> <strong>£60</strong>', false );
        $this->get( '/autumn-checklist' )->assertSee( 'Questions from our clients' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\GroveServiceProvider',
        ] );
    }
}
