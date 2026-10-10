<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\CounselDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class CounselDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/CounselDemo.php';

        ( new CounselDemo( 'counsel', 'counsel' ) )->seed();
        Tenancy::$callback = fn() => 'counsel';
        app()->forgetInstance( Tenancy::class );
    }


    public function testActionBarDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'counsel::firm'}->data->{'call-button'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="action-bar"', false );
    }


    public function testAttorneys() : void
    {
        $response = $this->get( '/attorneys' );

        $response->assertOk();
        $response->assertSee( 'Dr. Katharina Arndt' );
        $response->assertSee( 'Counsel and associates' );
    }


    public function testDemo() : void
    {
        $insights = Page::where( 'path', 'insights' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $insights->id ) );
        $this->assertSame( 8, Page::where( 'path', 'practice-areas' )->firstOrFail()->children()->count() );
        $this->assertSame( 'counsel', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-counsel', false );
        $response->assertSee( '"@type": "LegalService"', false );
        $response->assertSee( '"@type": "ReserveAction"', false );
        $response->assertSee( '"contactType": "emergency"', false );
        $response->assertSee( '"knowsAbout": ["Corporate law","M&A"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Friday"', false );
        $response->assertSee( 'class="action-bar"', false );
        $response->assertSee( 'class="call" href="tel:+496955504100"', false );
        $response->assertSee( 'class="disclaimer"', false );
        $response->assertSee( 'Practice areas' );
        $response->assertSee( '<li class="consult">', false );
        $response->assertSee( 'href="tel:+496955504199"', false );
    }


    public function testInsight() : void
    {
        $response = $this->get( '/dismissal-guide' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Step by step' );
        $response->assertSee( 'Questions from our clients' );
    }


    public function testPages() : void
    {
        foreach( ['/results', '/consultation', '/careers', '/privacy', '/imprint', '/insights'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/consultation' )->assertSee( 'Transparent fees' );
        $this->get( '/criminal-defense' )->assertSee( 'Questions from our clients' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\CounselServiceProvider',
        ] );
    }
}
