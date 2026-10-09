<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TonicDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class TonicDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/TonicDemo.php';

        ( new TonicDemo( 'tonic', 'tonic' ) )->seed();
        Tenancy::$callback = fn() => 'tonic';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'tonic::bar'}->data->{'action-bar'} = false;
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
        $this->assertSame( 'tonic', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-tonic', false );
        $response->assertSee( '"@type": "BarOrPub"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"acceptsReservations": true', false );
        $response->assertSee( '"hasMenu": "http://localhost/menu"', false );
        $response->assertSee( '"servesCuisine": ["Cocktails","Wine","Bar snacks"]', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Sunday"', false );
        $response->assertSee( 'Walk-ins welcome' );
        $response->assertSee( 'Over 18 only. Please drink responsibly.' );
        $response->assertSee( '<li class="book">', false );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+494023456780"', false );
        $response->assertSee( 'class="hours"', false );
        $response->assertSee( 'Altona Spritz' );
    }


    public function testMenu() : void
    {
        $response = $this->get( '/menu' );

        $response->assertOk();
        $response->assertSee( 'Negroni <strong>12.00</strong>', false );
        $response->assertSee( '<code>0.0</code>', false );
    }


    public function testPages() : void
    {
        foreach( ['/events', '/private-events', '/masterclasses', '/gift-cards', '/story', '/visit', '/journal', '/book', '/careers', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/story' )->assertSee( 'Ingrid Halvorsen' );
        $this->get( '/negroni-at-home' )->assertSee( 'Questions from our guests' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\TonicServiceProvider',
        ] );
    }
}
