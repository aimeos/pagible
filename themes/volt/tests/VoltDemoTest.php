<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Resource;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\VoltDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class VoltDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/VoltDemo.php';

        ( new VoltDemo( 'volt', 'volt' ) )->seed();
        Tenancy::$callback = fn() => 'volt';
        app()->forgetInstance( Tenancy::class );
    }


    public function testDemo() : void
    {
        $jobs = Page::where( 'path', 'jobs' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $jobs->id ) );
        $this->assertSame( 6, Page::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'volt', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-volt', false );
        $response->assertSee( '"@type": "Electrician"', false );
        $response->assertSee( '"name": "Portishead"', false );
        $response->assertSee( '"contactType": "emergency"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Saturday"', false );
        $response->assertSee( 'class="emergency"', false );
        $response->assertSee( '24/7 emergency service' );
        $response->assertSee( 'href="tel:+441174960999"', false );
        $response->assertSee( 'class="call-button" href="tel:+441174960457"', false );
        $response->assertSee( 'Clifton rewire' );
        $response->assertSee( 'Most booked' );
    }


    public function testJob() : void
    {
        $response = $this->get( '/clifton-rewire' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Before and after' );
        $response->assertSee( 'Step by step' );
    }


    public function testCallButtonDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'volt::business'}->data->{'call-button'} = false;
        $home->config = $config;
        Resource::updatePage( $home );

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\VoltServiceProvider',
        ] );
    }
}
