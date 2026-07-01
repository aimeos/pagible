<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Events\Viewed;
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

        // Stands in for the Pulse recorder so the flag-gated page-request watch,
        // which requires a consumer, fires.
        Event::listen( Viewed::class, fn() => null );
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


    public function testSearchDispatchesWithDurationForPulseRecorderWhenWatchOff() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );
        app( \Laravel\Pulse\Pulse::class )->register( [ThemeSearchedPulseRecorder::class => true] );
        Event::fake( [Searched::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( Searched::class, fn( Searched $e ) => $e->durationMs > 0.0 );
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


    public function testPageRequestDispatchesViewedOnCacheMiss() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [Viewed::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( Viewed::class, fn( Viewed $e ) =>
            $e->path === 'about' && $e->status === 200
        );
    }


    public function testPageRequestDispatchesViewedOnCacheHit() : void
    {
        config( ['cms.theme.watch' => true, 'cms.theme.cache' => 'array'] );
        Event::fake( [Viewed::class] );

        Cache::store( 'array' )->put( Page::key( 'about', '' ), 'cached-html' );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( Viewed::class, fn( Viewed $e ) =>
            $e->path === 'about' && $e->status === 200
        );
    }


    public function testPageRequestDispatchesViewedForNonSuccessStatus() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [Viewed::class] );

        $request = Request::create( '/missing', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'not found', 404 ) );

        Event::assertDispatched( Viewed::class, fn( Viewed $e ) =>
            $e->path === 'missing' && $e->status === 404
        );
    }


    public function testPageRequestDoesNotDispatchWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false] );
        Event::fake( [Viewed::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertNotDispatched( Viewed::class );
    }


    public function testSearchDispatchIsNotGatedBySampling() : void
    {
        // Search dispatches unconditionally (unlike page requests): consumers apply
        // sampling, so the event fires regardless of the sample rate.
        config( ['cms.theme.watch' => true, 'cms.watch.sample' => 0.0] );
        Event::fake( [Searched::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( Searched::class );
    }


    public function testPageRequestDoesNotDispatchWhenSampledOut() : void
    {
        config( ['cms.theme.watch' => true, 'cms.watch.sample' => 0.0] );
        Event::fake( [Viewed::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertNotDispatched( Viewed::class );
    }


    public function testPageRequestDispatchesWithoutWatchChannel() : void
    {
        // Page-request metrics are gated on the flag alone, so they work without a
        // watch log channel (Pulse needs none).
        config( ['cms.theme.watch' => true, 'cms.watch.channel' => null] );
        Event::fake( [Viewed::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( Viewed::class );
    }
}


class ThemeSearchedPulseRecorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Searched::class];


    public function record( mixed $event ) : void
    {
    }
}
