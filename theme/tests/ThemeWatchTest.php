<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Searched;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;


class ThemeWatchTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $seeder = TestSeeder::class;
    protected $connectionsToTransact = [];


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'scout.driver', 'collection' );
        $app['config']->set( 'mail.from.address', 'test@example.com' );
    }


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
    }


    public function testSearchDispatchesSearchedWhenWatchOn() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [Searched::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( Searched::class, fn( Searched $e ) =>
            $e->query === 'welcome'
            && $e->results >= 1
            && $e->page === 1
            && $e->domain === 'mydomain.tld'
            && $e->lang === 'en'
        );
    }


    public function testSearchDoesNotDispatchWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false] );
        Event::fake( [Searched::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertNotDispatched( Searched::class );
    }


    public function testContactDispatchesContactedWhenWatchOn() : void
    {
        config( ['cms.theme.watch' => true] );
        Mail::fake();
        Event::fake( [Contacted::class] );
        RateLimiter::clear( 'cms-contact' );

        $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] )->assertStatus( 200 );

        Event::assertDispatched( Contacted::class, fn( Contacted $e ) => $e->email === 'sender@google.com' );
    }


    public function testContactDoesNotDispatchWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false] );
        Mail::fake();
        Event::fake( [Contacted::class] );
        RateLimiter::clear( 'cms-contact' );

        $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] )->assertStatus( 200 );

        Event::assertNotDispatched( Contacted::class );
    }
}
