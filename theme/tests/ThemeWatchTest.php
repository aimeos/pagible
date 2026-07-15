<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Events\CmsRequest;
use Aimeos\Cms\Http\Middleware\Origin;
use Aimeos\Cms\Http\Middleware\ServeCachedPage;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\PageCache;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        Event::listen( CmsRequest::class, fn() => null );
    }


    protected function tearDown() : void
    {
        ServeCachedPage::bypassUsing( null );

        // Restore the watch config these tests mutate so a thrown assertion can't leak
        // state into later tests (the in-memory SQLite app persists across methods).
        config( [
            'app.url' => 'http://localhost',
            'cms.multidomain' => false,
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


    public function testSearchDispatchesWithDurationForPulseRecorderWhenWatchOff() : void
    {
        if( !class_exists( \Laravel\Pulse\Pulse::class ) ) {
            $this->markTestSkipped( 'Laravel Pulse is not installed.' );
        }

        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );
        app( \Laravel\Pulse\Pulse::class )->register( [ThemeSearchedPulseRecorder::class => true] );
        Event::fake( [CmsSearch::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( CmsSearch::class, fn( CmsSearch $e ) => $e->durationMs > 0.0 );
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


    public function testPageRequestDispatchesViewedOnCacheMiss() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [CmsRequest::class] );

        $request = Request::create( '/blog', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( CmsRequest::class, fn( CmsRequest $e ) =>
            $e->path === 'blog' && $e->status === 200
        );
    }


    public function testPageRequestDispatchesViewedOnCacheHit() : void
    {
        config( ['cms.theme.watch' => true, 'cms.theme.cache' => 'array'] );
        Event::fake( [CmsRequest::class] );

        $page = Page::where( 'path', 'blog' )->firstOrFail();

        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            $page,
        );

        $request = Request::create( '/blog', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( CmsRequest::class, fn( CmsRequest $e ) =>
            $e->path === 'blog' && $e->status === 200
        );
    }


    public function testAuthorizationHeaderBypassesPublicPageCache() : void
    {
        config( ['cms.theme.watch' => false, 'cms.theme.cache' => 'array'] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( '/about', 'GET' );
        $request->headers->set( 'Authorization', 'Bearer test' );

        $response = ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'private-body', 200 ) );

        $this->assertSame( 'private-body', $response->getContent() );
    }


    public function testCanonicalSingleDomainOriginUsesPublicPageCache() : void
    {
        config( [
            'app.url' => 'https://shop.example',
            'cms.multidomain' => false,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( 'https://shop.example/about', 'GET' );
        $response = ( new Origin() )->handle( $request, fn( Request $request ) =>
            ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        );

        $this->assertSame( 'cached-html', $response->getContent() );
    }


    public function testOtherSingleDomainSchemeBypassesPublicPageCache() : void
    {
        config( [
            'app.url' => 'https://shop.example',
            'cms.multidomain' => false,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( 'http://shop.example/about', 'GET' );
        $response = ( new Origin() )->handle( $request, fn( Request $request ) =>
            ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        );

        $this->assertSame( 'rendered-html', $response->getContent() );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
    }


    public function testOtherSingleDomainPortBypassesPublicPageCache() : void
    {
        config( [
            'app.url' => 'https://shop.example',
            'cms.multidomain' => false,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( 'https://shop.example:444/about', 'GET' );
        $response = ( new Origin() )->handle( $request, fn( Request $request ) =>
            ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        );

        $this->assertSame( 'rendered-html', $response->getContent() );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
    }


    public function testOtherSingleDomainHostBypassesPublicPageCache() : void
    {
        config( [
            'app.url' => 'https://shop.example',
            'cms.multidomain' => false,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( 'https://evil.example/about', 'GET' );
        $response = ( new Origin() )->handle( $request, fn( Request $request ) =>
            ( new ServeCachedPage() )->handle( $request, fn() => ( new Response( 'rendered-html', 200 ) )
                ->header( 'Cache-Control', 'public, s-maxage=300' )
                ->setExpires( now()->addMinutes( 5 ) ) )
        );

        $this->assertSame( 'rendered-html', $response->getContent() );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
        $this->assertFalse( $response->headers->hasCacheControlDirective( 'public' ) );
        $this->assertFalse( $response->headers->hasCacheControlDirective( 's-maxage' ) );
        $this->assertFalse( $response->headers->has( 'Expires' ) );
    }


    public function testMultiDomainCacheRequiresCanonicalSchemeAndPort() : void
    {
        config( [
            'app.url' => 'https://app.example:8443',
            'cms.multidomain' => true,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
            'shop.example',
        );

        $canonical = Request::create( 'https://shop.example:8443/about', 'GET' );
        $mismatched = Request::create( 'http://shop.example:8443/about', 'GET' );
        $this->assertSame( 'cached-html', ( new Origin() )->handle( $canonical,
            fn( Request $request ) => ( new ServeCachedPage() )
                ->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        )->getContent() );
        $response = ( new Origin() )->handle( $mismatched,
            fn( Request $request ) => ( new ServeCachedPage() )
                ->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        );

        $this->assertSame( 'rendered-html', $response->getContent() );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
    }


    public function testMissingAppHostBypassesPublicPageCache() : void
    {
        config( [
            'app.url' => '',
            'cms.multidomain' => false,
            'cms.theme.cache' => 'array',
            'cms.theme.watch' => false,
        ] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );

        $request = Request::create( 'http://shop.example/about', 'GET' );
        $response = ( new Origin() )->handle( $request, fn( Request $request ) =>
            ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'rendered-html', 200 ) )
        );

        $this->assertSame( 'rendered-html', $response->getContent() );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
    }


    public function testCustomIndicatorBypassesPublicPageCache() : void
    {
        config( ['cms.theme.watch' => false, 'cms.theme.cache' => 'array'] );
        PageCache::remember( fn() => ( new Response( 'cached-html', 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            'about',
        );
        ServeCachedPage::bypassUsing( fn( Request $request ) => $request->headers->has( 'X-Preview' ) );

        $request = Request::create( '/about', 'GET' );
        $request->headers->set( 'X-Preview', '1' );

        $response = ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'private-body', 200 ) );

        $this->assertSame( 'private-body', $response->getContent() );

        $request = Request::create( '/about', 'GET' );
        $request->headers->set( 'Authorization', 'Bearer test' );

        $response = ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'built-in-private-body', 200 ) );

        $this->assertSame( 'built-in-private-body', $response->getContent() );
    }


    public function testPageRequestDispatchesViewedForNonSuccessStatus() : void
    {
        config( ['cms.theme.watch' => true] );
        Event::fake( [CmsRequest::class] );

        $request = Request::create( '/missing', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'not found', 404 ) );

        Event::assertDispatched( CmsRequest::class, fn( CmsRequest $e ) =>
            $e->path === 'missing' && $e->status === 404
        );
    }


    public function testPageRequestDoesNotDispatchWhenWatchOff() : void
    {
        config( ['cms.theme.watch' => false] );
        Event::fake( [CmsRequest::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertNotDispatched( CmsRequest::class );
    }


    public function testSearchDispatchIsNotGatedBySampling() : void
    {
        // Search dispatches unconditionally (unlike page requests): consumers apply
        // sampling, so the event fires regardless of the sample rate.
        config( ['cms.theme.watch' => true, 'cms.watch.sample' => 0.0] );
        Event::fake( [CmsSearch::class] );

        $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'welcome', 'locale' => 'en', 'size' => 10] );
        ( new \Aimeos\Cms\Controllers\SearchController() )->index( $request, 'mydomain.tld' );

        Event::assertDispatched( CmsSearch::class );
    }


    public function testPageRequestDoesNotDispatchWhenSampledOut() : void
    {
        config( ['cms.theme.watch' => true, 'cms.watch.sample' => 0.0] );
        Event::fake( [CmsRequest::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertNotDispatched( CmsRequest::class );
    }


    public function testPageRequestDispatchesWithoutWatchChannel() : void
    {
        // Page-request metrics are gated on the flag alone, so they work without a
        // watch log channel (Pulse needs none).
        config( ['cms.theme.watch' => true, 'cms.watch.channel' => null] );
        Event::fake( [CmsRequest::class] );

        $request = Request::create( '/about', 'GET' );
        ( new ServeCachedPage() )->handle( $request, fn() => new Response( 'body', 200 ) );

        Event::assertDispatched( CmsRequest::class );
    }
}


class ThemeSearchedPulseRecorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsSearch::class];


    public function record( mixed $event ) : void
    {
    }
}
