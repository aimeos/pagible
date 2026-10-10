<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\MedicalDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class MedicalDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/MedicalDemo.php';

        ( new MedicalDemo( 'medical', 'medical' ) )->seed();
        Tenancy::$callback = fn() => 'medical';
        app()->forgetInstance( Tenancy::class );
    }


    public function testCallButtonDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'medical::practice'}->data->{'call-button'} = false;
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="call-button"', false );
    }


    public function testDemo() : void
    {
        $guides = Page::where( 'path', 'guides' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $guides->id ) );
        $this->assertSame( 9, Page::where( 'path', 'treatments' )->firstOrFail()->children()->count() );
        $this->assertSame( 'medical', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testGuide() : void
    {
        $response = $this->get( '/implant-guide' );

        $response->assertOk();
        $response->assertSee( 'type-blog', false );
        $response->assertSee( 'Step by step' );
        $response->assertSee( 'Questions from our patients' );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-medical', false );
        $response->assertSee( '"@type": "Dentist"', false );
        $response->assertSee( '"medicalSpecialty": "https://schema.org/Dentistry"', false );
        $response->assertSee( '"isAcceptingNewPatients": true', false );
        $response->assertSee( '"knowsLanguage": ["English","German","French","Turkish"]', false );
        $response->assertSee( '"dayOfWeek": "https://schema.org/Friday"', false );
        $response->assertSee( 'class="call-button" href="tel:+497614567230"', false );
        $response->assertSee( 'Professional cleaning' );
        $response->assertSee( 'Most booked' );
        $response->assertSee( '<li class="booking">', false );
        $response->assertSee( 'href="tel:+497614567299"', false );
    }


    public function testPages() : void
    {
        foreach( ['/patient-info', '/careers', '/privacy', '/dental-emergencies', '/appointment'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/implants' )->assertSee( 'Questions from our patients' );
    }


    public function testTeam() : void
    {
        $response = $this->get( '/team' );

        $response->assertOk();
        $response->assertSee( 'Dr. Anna Lindner' );
        $response->assertSee( 'Our practice' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\MedicalServiceProvider',
        ] );
    }
}
