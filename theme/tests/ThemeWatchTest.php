<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Http\Middleware\ServeCachedPage;
use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;


class ThemeWatchTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected string $seeder = TestSeeder::class;

    /**
     * @var list<string>
     */
    protected array $connectionsToTransact = [];


    protected function setUp() : void
    {
        parent::setUp();

        // Stands in for the Pulse recorder so metric-only observations have a consumer.
        Event::listen( Observed::class, fn() => null );
    }


    protected function tearDown() : void
    {
        // Restore the watch config these tests mutate so a thrown assertion can't leak
        // state into later tests (the in-memory SQLite app persists across methods).
        config( [
            'cms.theme.watch' => false,
            'cms.watch.sample' => 1.0,
            'cms.watch.channel' => 'cms',
            'cms.theme.cache' => 'array',
        ] );

        parent::tearDown();
    }


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'scout.driver', 'collection' );
        $app['config']->set( 'mail.from.address', 'test@example.com' );
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
    }


    public function testSearchDispatchesSearchedWhenWatchOn() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [CmsSearch::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( CmsSearch::class, fn( CmsSearch $e ) =>
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
        Event::fake( [CmsSearch::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertNotDispatched( CmsSearch::class );
    }


    public function testSearchDispatchesWithDurationForObserverWhenWatchOff() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );
        Event::fake( [Observed::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'search'
            && $e->action === 'theme:search'
            && $e->durationMs > 0.0
            && $e->dimensions === ['domain' => 'mydomain.tld', 'lang' => 'en']
            && $e->sample
        );
    }


    public function testContactDispatchesContactedWhenWatchOn() : void
    {
        config( ['cms.theme.watch' => true] );
        Mail::fake();
        Event::fake( [CmsContact::class] );
        RateLimiter::clear( 'cms-contact' );

        $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] )->assertStatus( 200 );

        Event::assertDispatched( CmsContact::class, fn( CmsContact $e ) => $e->email === 'sender@google.com' );
    }


    public function testContactDoesNotDispatchWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false] );
        Mail::fake();
        Event::fake( [CmsContact::class] );
        RateLimiter::clear( 'cms-contact' );

        $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] )->assertStatus( 200 );

        Event::assertNotDispatched( CmsContact::class );
    }


    public function testContactDispatchesObservedWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false, 'cms.watch.channel' => null] );
        Mail::fake();
        Event::fake( [Observed::class] );
        RateLimiter::clear( 'cms-contact' );

        $this->post( route( 'cms.api.contact' ), [
            'name' => 'Test User',
            'email' => 'sender@google.com',
            'message' => 'Hello, this is a test message.',
        ] )->assertStatus( 200 );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'contact'
            && $e->action === 'theme:contact'
            && $e->dimensions === []
        );
    }


    public function testPageRequestDispatchesForObserverWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false, 'cms.watch.channel' => null, 'cms.watch.sample' => 0.0] );
        Event::fake( [Observed::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'request'
            && $e->action === 'theme:view'
            && $e->dimensions['path'] === '/about'
            && $e->dimensions['status'] === 200
            && $e->sample
        );
    }


    public function testPageRequestDispatchesViewedOnCacheHit() : void
    {
        config( ['cms.theme.watch' => true, 'cms.theme.cache' => 'array'] );
        Event::fake( [Observed::class] );

        Cache::store( 'array' )->put( Page::key( '', '' ), 'cached-html' );

        $request = Request::create( '/', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'request'
            && $e->dimensions['path'] === '/'
            && $e->dimensions['status'] === 200
        );
    }


    public function testPageRequestDispatchesViewedForNonSuccessStatus() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [Observed::class] );

        $request = Request::create( '/missing', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'not found', 404 ) );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'request'
            && $e->dimensions['path'] === '*'
            && $e->dimensions['status'] === 404
            && $e->dimensions['domain'] === ''
        );
    }


    public function testSearchDispatchIsNotGatedBySampling() : void
    {
        // Consumers apply sampling, so the event fires regardless of the sample rate.
        config( ['cms.theme.watch' => true, 'cms.watch.sample' => 0.0] );
        Event::fake( [CmsSearch::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( CmsSearch::class );
    }
}
