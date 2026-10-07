<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\SolidDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class SolidDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/SolidDemo.php';

        ( new SolidDemo( 'solid', 'solid' ) )->seed();
        Tenancy::$callback = fn() => 'solid';
        app()->forgetInstance( Tenancy::class );
    }


    public function testDemo() : void
    {
        $projects = Page::where( 'path', 'projects' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $projects->id ) );
        $this->assertSame( 3, Page::where( 'path', 'services' )->firstOrFail()->children()->count() );
        $this->assertSame( 'solid', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-solid', false );
        $response->assertSee( '"@type": "GeneralContractor"', false );
        $response->assertSee( '"name": "Harrogate"', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Saturday"', false );
        $response->assertSee( 'class="call-button" href="tel:+441134960123"', false );
        $response->assertSee( 'Roundhay extension' );
    }


    public function testProject() : void
    {
        $response = $this->get( '/roundhay-extension' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Before and after' );
        $response->assertSee( 'Build phases' );
    }


    public function testCallButtonDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'solid::contractor'}->data->{'call-button'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\SolidServiceProvider',
        ] );
    }
}
