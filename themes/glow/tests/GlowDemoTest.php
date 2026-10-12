<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\GlowDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class GlowDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/GlowDemo.php';

        ( new GlowDemo( 'glow', 'glow' ) )->seed();
        Tenancy::$callback = fn() => 'glow';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'glow::salon'}->data->{'action-bar'} = false;
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
        $this->assertSame( 'glow', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-glow', false );
        $response->assertSee( '"@type": "BeautySalon"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"@type": "OfferCatalog"', false );
        $response->assertSee( '"url": "http://localhost/treatments"', false );
        $response->assertSee( '"priceRange": "€€"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Monday"', false );
        $response->assertDontSee( 'servesCuisine', false );
        $response->assertSee( 'Your first facial includes a free skin analysis' );
        $response->assertSee( 'Please cancel or move your appointment at least 24 hours before.' );
        $response->assertSee( '<li class="book">', false );
        $response->assertSee( 'Book now' );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+494046892210"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Hannah Weiss' );
    }


    public function testJournal() : void
    {
        $response = $this->get( '/journal' );

        $response->assertOk();
        $response->assertSee( 'class="list-item"', false );
        $response->assertSee( 'Which Facial Is Right for Your Skin?' );
        $response->assertSee( '<a href="http://localhost/journal" class="active">', false );
    }


    public function testPages() : void
    {
        foreach( ['/treatments', '/facials', '/nails', '/lashes-brows', '/membership', '/team', '/careers', '/new-clients', '/gift-cards', '/journal', '/visit', '/book', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/team' )->assertSee( 'Lukas Petersen' );
        $this->get( '/membership' )->assertSee( 'What members pay' );
        $this->get( '/gift-cards' )->assertSee( 'Give an hour of <em>glow</em>', false );
        $this->get( '/winter-skin' )->assertSee( 'Questions from our clients' );
    }


    public function testTreatments() : void
    {
        $response = $this->get( '/treatments' );

        $response->assertOk();
        $response->assertSee( 'The full menu' );
        $response->assertSee( 'Brazilian <code>bestseller</code> <strong>€55</strong>', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\GlowServiceProvider',
        ] );
    }
}
