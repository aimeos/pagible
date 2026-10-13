<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\LaunchDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class LaunchDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/LaunchDemo.php';

        ( new LaunchDemo( 'launch', 'launch' ) )->seed();
        Tenancy::$callback = fn() => 'launch';
        app()->forgetInstance( Tenancy::class );
    }


    public function testAnnouncementDisabled() : void
    {
        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $config = $home->config;
        $config->{'launch::product'}->data->announcement = '';
        $home->config = $config;
        $home->saveQuietly();

        $this->get( '/' )->assertDontSee( 'class="topbar"', false );
    }


    public function testAuthRoutes() : void
    {
        \Illuminate\Support\Facades\Route::get( '/login', fn() => '' )->name( 'login' );
        \Illuminate\Support\Facades\Route::get( '/register', fn() => '' )->name( 'register' );
        app( 'router' )->getRoutes()->refreshNameLookups();

        $response = $this->get( '/' );

        $response->assertSee( '<a href="http://localhost/login" title="Login" aria-label="Login">', false );
        $response->assertSee( '<a href="http://localhost/register">Get started</a>', false );
    }


    public function testBlog() : void
    {
        $response = $this->get( '/blog' );

        $response->assertOk();
        $response->assertSee( 'On-Call Without Burnout: Seven Changes That Make Rotations Fair' );
        $response->assertSee( '<a href="http://localhost/blog" class="active">', false );
    }


    public function testChangelog() : void
    {
        $response = $this->get( '/changelog' );

        $response->assertOk();
        $response->assertSee( 'timeline', false );
        $response->assertSee( 'Oct 7, 2026' );
        $response->assertSee( 'Relay 3.0: AI incident summaries' );
        $response->assertSee( '<code>relay/relay</code>', false );
    }


    public function testDemo() : void
    {
        $blog = Page::where( 'path', 'blog' )->firstOrFail();
        $items = Page::where( 'type', 'blog' )->get();

        $this->assertCount( 3, $items );
        $this->assertTrue( $items->every( fn( $item ) => $item->parent_id === $blog->id ) );
        $this->assertSame( 'launch', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-launch', false );
        $response->assertSee( '"@type": "SoftwareApplication"', false );
        $response->assertSee( '"applicationCategory": "DeveloperApplication"', false );
        $response->assertSee( '"ratingValue": "4.8"', false );
        $response->assertSee( '"priceCurrency": "USD"', false );
        $response->assertSee( 'class="topbar"', false );
        $response->assertSee( 'Relay 3.0 is here: AI incident summaries' );
        $response->assertSee( '<li class="login">', false );
        $response->assertSee( '<li class="signup">', false );
        $response->assertSee( '<a href="http://localhost/contact">Get started</a>', false );
        $response->assertSee( 'Get started' );
        $response->assertSee( 'System status' );
        $response->assertSee( 'SOC 2 Type II certified · GDPR compliant · Data hosted in the EU and US' );
        $response->assertSee( 'Teams sleep better with Relay' );
        $response->assertSee( 'Frequently asked questions' );
    }


    public function testPages() : void
    {
        foreach( ['/features', '/pricing', '/customers', '/changelog', '/blog', '/about', '/contact', '/signup', '/welcome', '/imprint', '/privacy'] as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/about' )->assertSee( 'Nora Castillo' );
        $this->get( '/customers' )->assertSee( 'Northwind cut alert noise by 80%' );
        $this->get( '/status-pages' )->assertSee( 'Questions from readers' );
    }


    public function testPricing() : void
    {
        $response = $this->get( '/pricing' );

        $response->assertOk();
        $response->assertSee( 'Most popular' );
        $response->assertSee( 'data-unit="per month"', false );
        $response->assertSee( 'data-unit="per year"', false );
        $response->assertSee( '$190' );
        $response->assertSee( 'Talk to sales' );
        $response->assertSee( 'Compare plans' );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\LaunchServiceProvider',
        ] );
    }
}
