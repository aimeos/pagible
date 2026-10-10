<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\PawsDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class PawsDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/PawsDemo.php';

        ( new PawsDemo( 'paws', 'paws' ) )->seed();
        Tenancy::$callback = fn() => 'paws';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'paws::clinic'}->data->{'call-button'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="action-bar"', false );
    }


    public function testDemo() : void
    {
        $guides = Page::where( 'path', 'guides' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $guides->id ) );
        $this->assertSame( 9, Page::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'paws', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testGuide() : void
    {
        $response = $this->get( '/puppy-guide' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Step by step' );
        $response->assertSee( 'Questions from pet owners' );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-paws', false );
        $response->assertSee( '"@type": "VeterinaryCare"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"contactType": "emergency"', false );
        $response->assertSee( '"isAcceptingNewPatients": true', false );
        $response->assertSee( '"knowsAbout": ["Dogs","Cats","Rabbits","Guinea pigs","Ferrets"]', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Saturday"', false );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+494055501230"', false );
        $response->assertSee( 'Health plans' );
        $response->assertSee( 'Most chosen' );
        $response->assertSee( '<li class="booking">', false );
        $response->assertSee( 'href="tel:+494055501299"', false );
    }


    public function testPages() : void
    {
        foreach( ['/new-clients', '/careers', '/privacy', '/imprint', '/emergencies', '/appointment'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/small-pets' )->assertSee( 'Questions from pet owners' );
    }


    public function testTeam() : void
    {
        $response = $this->get( '/team' );

        $response->assertOk();
        $response->assertSee( 'Dr. Lena Brandt' );
        $response->assertSee( 'Our clinic' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\PawsServiceProvider',
        ] );
    }
}
