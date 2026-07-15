<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\PageCache;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;


class PageControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User();
        $this->user->name = 'Test';
        $this->user->email = 'test@example.com';
        $this->user->tenant_id = 'test';
        $this->user->cmsperms = ['admin'];
        Access::using( fn() => ['frontend.member'] );
        AuthenticationException::redirectUsing( fn() => '/login' );
    }


    protected function tearDown(): void
    {
        AuthenticationException::redirectUsing( fn() => null );

        parent::tearDown();
    }


    public function testLatestFindsChangedPath()
    {
        $page = Page::where( 'tag', 'blog' )->firstOrFail();

        // Save with a new path (mimics admin panel which always sends domain)
        Resource::savePage(
            $page->id,
            ['path' => 'new-blog-path', 'domain' => $page->domain ?? ''],
            $this->user,
        );

        // Now try to access the page via the new path (as an editor would)
        $response = $this->actingAs( $this->user )->get( '/new-blog-path' );
        $response->assertStatus( 200 );
    }


    public function testPublishingChangedRouteInvalidatesPreviousAndCurrentCompletePage(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $oldPath = $page->path;
        $newPath = 'renamed-page';

        $this->cache( $oldPath, 'old-route', $page->domain );
        $this->cache( $newPath, 'new-route', $page->domain );

        Resource::savePage( $page->id, ['path' => $newPath], $this->user );
        Resource::publish( Page::class, [$page->id], $this->user->email );

        $this->assertNull( PageCache::response( $oldPath, $page->domain ) );
        $this->assertNull( PageCache::response( $newPath, $page->domain ) );
    }


    public function testDroppingPageInvalidatesCompleteSubtreeCache(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $parent = Resource::addPage( [
            'lang' => 'en', 'name' => 'Parent', 'title' => 'Parent', 'path' => 'cache-parent',
            'content' => [],
        ], $this->user, parent: (string) $root->id );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'cache-child',
            'content' => [],
        ], $this->user, parent: (string) $parent->id );

        $this->cache( $parent, 'cached-parent' );
        $this->cache( $child, 'cached-child' );

        Resource::drop( Page::class, [(string) $parent->id], 'test@example.com' );

        $this->assertNull( PageCache::response( $parent ) );
        $this->assertNull( PageCache::response( $child ) );
    }


    public function testLatestFindsChangedPathNoDomain()
    {
        $page = Page::where( 'tag', 'article' )->firstOrFail();

        // Save with a new path (no domain in input — e.g. MCP tool)
        Resource::savePage(
            $page->id,
            ['path' => 'changed-article-path'],
            $this->user,
        );

        // Try to access via new path
        $response = $this->actingAs( $this->user )->get( '/changed-article-path' );
        $response->assertStatus( 200 );
    }


    public function testLatestFindsChangedPathWithDomainPage()
    {
        // Home page has domain='mydomain.tld' in the seeder
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $this->assertEquals( 'mydomain.tld', $page->domain );

        // Admin panel save sends the page's domain
        Resource::savePage(
            $page->id,
            ['path' => 'new-home', 'domain' => $page->domain],
            $this->user,
        );

        // Without multidomain config, the route has no {domain} parameter,
        // so $domain defaults to '' in the controller
        $response = $this->actingAs( $this->user )->get( '/new-home' );
        $this->assertNotEquals( 404, $response->status() );
    }


    public function testLatestFindsExistingVersionWithoutDomain()
    {
        // Create a page with a version that has no domain in data (legacy/importer case)
        $page = Page::forceCreate([
            'lang' => 'en',
            'name' => 'Test',
            'title' => 'Test Page',
            'path' => 'test-page',
            'status' => 1,
            'editor' => 'test',
        ]);

        $version = $page->versions()->forceCreate([
            'data' => ['name' => 'Test', 'path' => 'test-page', 'status' => 1],
            'aux' => [],
            'published' => true,
            'editor' => 'test',
        ]);
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();

        // Now save with a new path (no domain in input)
        Resource::savePage(
            $page->id,
            ['path' => 'new-test-page'],
            $this->user,
        );

        // Verify the version data now includes domain
        $page->refresh();
        $latest = Version::find( $page->latest_id );
        $this->assertArrayHasKey( 'domain', (array) $latest->data );

        // Try to access via new path
        $response = $this->actingAs( $this->user )->get( '/new-test-page' );
        $response->assertStatus( 200 );
    }


    public function testLatestShowsUnpublishedReferencedElement()
    {
        // Shared element that was never published: its own "data" column is still
        // empty, the draft content lives only in the latest (unpublished) version.
        $element = Element::forceCreate([
            'lang' => 'en',
            'type' => 'text',
            'name' => 'Draft element',
            'editor' => 'test',
        ]);
        $version = $element->versions()->forceCreate([
            'lang' => 'en',
            'data' => ['type' => 'text', 'name' => 'Draft element', 'data' => ['text' => 'DRAFT_ELEMENT_TEXT']],
            'published' => false,
            'editor' => 'test',
        ]);
        $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();

        // Deactivated, never-published page referencing the unpublished element.
        $page = Page::forceCreate([
            'lang' => 'en',
            'name' => 'Draft preview',
            'title' => 'Draft preview',
            'path' => 'draft-preview',
            'status' => 0,
            'editor' => 'test',
        ]);
        $pageVersion = $page->versions()->forceCreate([
            'lang' => 'en',
            'data' => ['name' => 'Draft preview', 'path' => 'draft-preview', 'status' => 0],
            'aux' => ['content' => [
                ['id' => 'el1', 'type' => 'reference', 'group' => 'main', 'refid' => $element->id],
            ]],
            'published' => false,
            'editor' => 'test',
        ]);
        $page->forceFill( ['latest_id' => $pageVersion->id] )->saveQuietly();
        $pageVersion->elements()->attach( $element->id );

        // Editor preview must render the element's draft content
        $response = $this->actingAs( $this->user )->get( '/draft-preview' );
        $response->assertStatus( 200 );
        $response->assertSee( 'DRAFT_ELEMENT_TEXT' );
    }


    public function testHeroRendersImageList()
    {
        $jpeg = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $tiff = File::where( 'mime', 'image/tiff' )->firstOrFail();
        $root = Page::where( 'tag', 'root' )->firstOrFail();

        Resource::addPage( [
            'lang' => 'en',
            'name' => 'Hero images',
            'title' => 'Hero images',
            'path' => 'hero-images',
            'status' => 1,
            'content' => [[
                'id' => 'hero-images',
                'type' => 'hero',
                'group' => 'main',
                'data' => [
                    'title' => 'Hero images',
                    'files' => [
                        ['id' => $jpeg->id, 'type' => 'file'],
                        ['id' => $tiff->id, 'type' => 'file'],
                    ],
                ],
            ]],
        ], $this->user, parent: $root->id );

        $response = $this->actingAs( $this->user )->get( '/hero-images' );

        $response->assertStatus( 200 );
        $response->assertSee( 'second multiple swiffy-slider', false );
        $response->assertSee( 'slider-container', false );
        $response->assertSee( 'slideshow.css', false );
        $response->assertSee( 'slideshow.js', false );
        $response->assertSee( 'Test file description', false );
        $response->assertSee( 'Test TIFF file description', false );
        $this->assertSame( 2, substr_count( (string) $response->getContent(), '<picture' ) );
    }


    public function testHeroRendersSingleImageArray()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $root = Page::where( 'tag', 'root' )->firstOrFail();

        Resource::addPage( [
            'lang' => 'en',
            'name' => 'Hero files',
            'title' => 'Hero files',
            'path' => 'hero-files',
            'status' => 1,
            'content' => [[
                'id' => 'hero-files',
                'type' => 'hero',
                'group' => 'main',
                'data' => [
                    'title' => 'Hero files',
                    'files' => [['id' => $file->id, 'type' => 'file']],
                ],
            ]],
        ], $this->user, parent: $root->id );

        $response = $this->actingAs( $this->user )->get( '/hero-files' );

        $response->assertStatus( 200 );
        $response->assertSee( 'class="second"', false );
        $response->assertDontSee( 'class="second multiple"', false );
        $this->assertSame( 1, substr_count( (string) $response->getContent(), '<picture' ) );
    }


    public function testAnonymousCacheablePageHasNoCookies()
    {
        Page::forceCreate([
            'lang' => 'en',
            'name' => 'Cacheable',
            'title' => 'Cacheable Page',
            'path' => 'cacheable',
            'status' => 1,
            'cache' => 5,
            'editor' => 'test',
            'content' => [
                ['id' => 'h1', 'type' => 'heading', 'group' => 'main', 'data' => ['title' => 'Hello']],
            ],
        ]);

        // A cacheable page is served (or rendered then stored) without per-visitor
        // cookies, so a CDN can cache it.
        $response = $this->get( '/cacheable' );

        $response->assertStatus( 200 );
        $this->assertStringContainsString( 'public', (string) $response->headers->get( 'Cache-Control' ) );
        $response->assertCookieMissing( config( 'session.cookie' ) );
        $this->assertNotNull( PageCache::response( 'cacheable', '', true ) );
    }


    public function testRenderInProgressServesStaleCompletePage(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Stale',
            'title' => 'Stale',
            'path' => 'stale',
            'status' => 1,
            'cache' => 5,
            'editor' => 'test',
        ] );
        $html = 'stale-complete-page';
        $key = $this->cacheKey( $page );

        $this->putCache( $key, $html, now()->subSecond() );
        $store = Cache::store( config( 'cms.theme.cache', 'file' ) )->getStore();

        if( !$store instanceof LockProvider ) {
            $this->fail( 'Theme cache store must support locks' );
        }

        $lock = $store->lock( $key . ':render', 10 );
        $lock->get();

        try {
            $response = $this->get( '/stale' );
        } finally {
            $lock->release();
        }

        $response->assertOk();
        $response->assertSee( 'stale-complete-page', false );
        $response->assertCookieMissing( config( 'session.cookie' ) );
    }


    public function testCompletePageCacheHitDoesNotQueryDatabase(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $this->cache( $page, 'cached-without-database' );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get( '/hidden' );

        $response->assertOk();
        $response->assertSee( 'cached-without-database', false );
        $this->assertSame( [], DB::getQueryLog() );
    }


    public function testRouteCacheKeysEncodePartsUnambiguously(): void
    {
        $method = new \ReflectionMethod( PageCache::class, 'routeKey' );

        $this->assertNotSame(
            $method->invoke( null, 'a', 'b', 'c|d' ),
            $method->invoke( null, 'a|b', 'c', 'd' ),
        );
    }


    public function testCompletePageCacheRejectsNonPublicDirectives(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $directives = ['not-public', 'private, public', 'public, no-store', 'public, no-cache'];

        foreach( $directives as $idx => $directive )
        {
            $path = 'cache-directive-' . $idx;

            PageCache::remember( fn() => ( new Response( 'private-html', 200 ) )
                ->header( 'Cache-Control', $directive )
                ->setExpires( now()->addMinutes( 5 ) ),
                $path,
            );

            $this->assertNull( PageCache::response( $path ), $directive );
        }
    }


    public function testRenderContentionWithoutStalePageWaitsAndWritesCache(): void
    {
        config( ['cms.theme.cache' => 'array', 'cms.theme.lock' => 1] );
        $key = $this->cacheKey( 'contended' );
        $store = Cache::store( config( 'cms.theme.cache', 'file' ) )->getStore();

        if( !$store instanceof LockProvider ) {
            $this->fail( 'Theme cache store must support locks' );
        }

        $lock = $store->lock( $key . ':render', 1 );
        $this->assertTrue( $lock->get() );

        try {
            $response = PageCache::remember( fn() => ( new Response( 'uncached-render', 200 ) )
                ->header( 'Cache-Control', 'public' )
                ->setExpires( now()->addMinutes( 5 ) ),
                'contended',
            );
        } finally {
            $lock->release();
        }

        $this->assertInstanceOf( Response::class, $response );
        $this->assertSame( 'uncached-render', $response->getContent() );
        $this->assertSame( 'uncached-render', PageCache::response( 'contended' )?->getContent() );
    }


    public function testAccessInvalidationDoesNotWaitForActiveRenderLease(): void
    {
        config( ['cms.theme.cache' => 'array', 'cms.theme.lock' => 1] );

        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $key = $this->cacheKey( $page );
        $this->cache( $page, 'public-html' );
        $store = Cache::store( config( 'cms.theme.cache', 'file' ) )->getStore();

        if( !$store instanceof LockProvider ) {
            $this->fail( 'Theme cache store must support locks' );
        }

        $lock = $store->lock( $key . ':render', 1 );
        $this->assertTrue( $lock->get() );
        $start = hrtime( true );

        try {
            PageAccess::restrict( [$page->id], null );
        } finally {
            $lock->release();
        }

        $this->assertLessThan( 750, ( hrtime( true ) - $start ) / 1_000_000 );
        $this->assertNull( PageCache::response( $page ) );
    }


    public function testAccessInvalidationDeletesWhileRenderLeaseIsHeld(): void
    {
        config( ['cms.theme.cache' => 'array', 'cms.theme.lock' => 1] );

        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $key = $this->cacheKey( $page );
        $this->cache( $page, 'public-html' );
        $store = Cache::store( config( 'cms.theme.cache', 'file' ) )->getStore();

        if( !$store instanceof LockProvider ) {
            $this->fail( 'Theme cache store must support locks' );
        }

        $lock = $store->lock( $key . ':render', 30 );
        $this->assertTrue( $lock->get() );

        try {
            PageAccess::restrict( [$page->id], null );
            $this->assertNull( PageCache::response( $page ) );
            $this->get( '/hidden' )->assertRedirect( '/login' );
        } finally {
            $lock->release();
        }
    }


    public function testRestrictedPageRedirectsGuestAndAllowsPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], ['frontend.member'] );

        $guest = $this->get( '/hidden' );
        $guest->assertRedirect( '/login' );
        $guest->assertSessionHas( 'url.intended', 'http://localhost/hidden' );

        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $user->cmsperms = [];
        \Illuminate\Support\Facades\Gate::define( 'frontend.member', fn() => true );

        $response = $this->actingAs( $user )->get( '/hidden' );
        $response->assertOk();
        $this->assertStringContainsString( 'private', (string) $response->headers->get( 'Cache-Control' ) );
    }


    public function testRestrictedPageReturnsUnauthorizedForJsonGuest(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], ['frontend.member'] );

        $this->getJson( '/hidden' )->assertUnauthorized();
    }


    public function testGuestRedirectsWhenPageBecomesRestrictedDuringRender(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $restricted = false;

        View::composer( '*', function() use ( &$restricted, $page ) {
            if( !$restricted ) {
                $restricted = true;
                PageAccess::restrict( [$page->id], null );
            }
        } );

        $this->get( '/hidden' )->assertRedirect( '/login' );
    }


    public function testRestrictedPageForbidsAuthenticatedUserWithoutPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], ['frontend.member'] );

        $user = new \App\Models\User();
        $user->id = 43;
        $user->tenant_id = 'test';
        $user->cmsperms = [];
        \Illuminate\Support\Facades\Gate::define( 'frontend.member', fn() => false );

        $this->actingAs( $user )->get( '/hidden' )->assertForbidden();
    }


    public function testEditorFromAnotherTenantCannotBypassPageAccess(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], null );
        $this->user->tenant_id = 'other';

        $this->actingAs( $this->user )->get( '/hidden' )->assertForbidden();
    }


    public function testRestrictedPageIsHiddenFromGuestNavigation(): void
    {
        $blog = Page::where( 'path', 'blog' )->firstOrFail();
        PageAccess::restrict( [$blog->id], null );

        $response = $this->get( '/hidden' );

        $response->assertOk();
        $response->assertDontSee( 'href="http://localhost/blog"', false );
    }


    public function testRedirectUsesOneQuery(): void
    {
        Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Redirect',
            'title' => 'Redirect',
            'path' => 'redirect',
            'to' => '/target',
            'status' => 1,
            'editor' => 'test',
        ] );

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get( '/redirect' )->assertRedirect( '/target' );

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testMissingPageIsResolvedWithOneQuery(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->get( '/does-not-exist' );
        $response->assertNotFound();
        $response->assertCookieMissing( config( 'session.cookie' ) );

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testRestrictedGuestPreflightUsesOneQuery(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], null );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get( '/hidden' )->assertRedirect( '/login' );

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testUncachedPageUsesFullWebSession()
    {
        Page::forceCreate([
            'lang' => 'en',
            'name' => 'Dynamic',
            'title' => 'Dynamic Page',
            'path' => 'dynamic',
            'status' => 1,
            'cache' => 0,
            'editor' => 'test',
            'content' => [
                ['id' => 'h1', 'type' => 'heading', 'group' => 'main', 'data' => ['title' => 'Hello']],
            ],
        ]);

        // Uncacheable pages render through the full "web" stack, so a session is
        // started and its cookie is kept.
        $response = $this->get( '/dynamic' );

        $response->assertStatus( 200 );
        $response->assertCookie( config( 'session.cookie' ) );
    }


    public function testCsrfEndpointStartsSessionForGuest()
    {
        // The token endpoint must still start a session on demand so the lazy
        // CSRF flow works for cached pages.
        $response = $this->get( '/cmsapi/csrf' );

        $response->assertStatus( 200 );
        $response->assertJsonStructure( ['token'] );
        $response->assertCookie( config( 'session.cookie' ) );
    }


    private function cacheKey( Page|string $page, string $domain = '' ): string
    {
        $key = ( new \ReflectionMethod( PageCache::class, 'key' ) )->invoke( null, $page, $domain );

        if( !is_string( $key ) ) {
            $this->fail( 'Expected a string cache key' );
        }

        return $key;
    }


    private function cache( Page|string $page, string $html, string $domain = '' ): void
    {
        PageCache::remember( fn() => ( new Response( $html, 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            $page,
            $domain,
        );
    }


    private function putCache( string $key, string $html, \DateTimeInterface $expires ): void
    {
        ( new \ReflectionMethod( PageCache::class, 'put' ) )->invoke( null, $key, $html, $expires );
    }
}
