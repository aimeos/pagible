<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\PageCache;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Validation;
use Database\Seeders\TestSeeder;
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
    }


    public function testEditorAssetsAreOptional()
    {
        // The monorepo discovers the admin package during tests. Restrict the shared
        // namespace to theme views to exercise a standalone theme installation.
        view()->replaceNamespace( 'cms', dirname( __DIR__ ) . '/views' );

        $response = $this->actingAs( $this->user )->get( '/blog' );

        $response->assertStatus( 200 );
        $response->assertDontSee( 'vendor/cms/admin/editor', false );
        $response->assertDontSee( 'stats.js', false );
    }


    public function testWebsiteTitleUsesPageTreeConfig()
    {
        config( ['app.name' => 'Application name'] );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $root->variant->update( ['config' => $this->websiteConfig( 'Tree website' )] );

        $response = $this->get( '/blog?website=inherited' );

        $response->assertOk();
        $response->assertSee( 'title="Tree website"', false );
        $response->assertSee( '"name": "Tree website"', false );
        $response->assertSee( '&copy; ' . date( 'Y' ) . ' Tree website', false );
        $response->assertDontSee( 'Application name' );
        $this->assertMatchesRegularExpression(
            '#<li class="brand">\s*<a[^>]*>\s*Tree website\s*</a>#',
            (string) $response->getContent(),
        );

        $page = PageView::where( 'tag', 'blog' )->firstOrFail();
        $page->variant->update( ['config' => $this->websiteConfig( 'Section website' )] );

        $response = $this->get( '/blog?website=nearest' );

        $response->assertOk();
        $response->assertSee( 'title="Section website"', false );
        $response->assertDontSee( 'Tree website' );
    }


    public function testWebsiteTitleUsesRootPageNameAsFallback()
    {
        config( ['app.name' => 'Application name'] );

        $response = $this->get( '/blog' );

        $response->assertOk();
        $response->assertSee( 'title="Home"', false );
        $response->assertDontSee( 'Application name' );
    }


    public function testSearchUsesPageLanguage()
    {
        $page = \Aimeos\Cms\Models\PageView::where( 'path', 'blog' )->firstOrFail();

        $response = $this->get( '/blog' );

        $response->assertOk();
        $response->assertSee( 'cmsapi/search?q=_term_&amp;locale=' . $page->lang . '"', false );
        $response->assertSee( 'data-no-results="No results found"', false );
        $response->assertSee( '"inLanguage": "' . $page->lang . '"', false );
        $response->assertSee( 'dir="ltr"', false );
        $response->assertHeader( 'Content-Language', $page->lang );
    }


    public function testRegionalRtlLanguageUsesRtlDirection()
    {
        \Aimeos\Cms\Models\PageView::where( 'path', 'blog' )->firstOrFail()->variant->update( ['lang' => 'ar-EG'] );

        $response = $this->get( '/blog' );

        $response->assertOk();
        $response->assertSee( 'lang="ar-EG" dir="rtl"', false );
    }


    public function testPaginatorPageUsesSelfCanonicalUrl()
    {
        $this->blogList();
        $response = $this->get( '/blog?p=2&utm_source=campaign' );

        $response->assertOk();
        $response->assertSee( '<link rel="canonical" href="http://localhost/blog?p=2"', false );
        $response->assertDontSee( 'utm_source=campaign', false );
    }


    public function testManualCanonicalUsesCurrentPaginatorPage()
    {
        $this->blogList()->variant->update( ['meta' => [
            'canonical' => [
                'type' => 'canonical',
                'data' => ['url' => 'https://example.com/journal?lang=en'],
                'files' => [],
            ],
        ]] );

        $response = $this->get( '/blog?p=2' );

        $response->assertOk();
        $response->assertSee( '<link rel="canonical" href="https://example.com/journal?lang=en&amp;p=2"', false );
        $response->assertDontSee( '<link rel="canonical" href="http://localhost/blog', false );
    }


    public function testDetailPageIgnoresPaginatorParameterInCanonicalUrl()
    {
        $page = PageView::where( 'tag', 'article' )->firstOrFail();
        $response = $this->get( '/' . $page->path . '?p=2' );

        $response->assertOk();
        $response->assertSee( '<link rel="canonical" href="http://localhost/' . $page->path . '"', false );
        $response->assertDontSee( '<link rel="canonical" href="http://localhost/' . $page->path . '?p=2"', false );
    }


    public function testWebsiteTitleUsesPublishedAndLatestPageTreeVersions()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        Pages::save( $root->id, ['config' => $this->websiteConfig( 'Published website' )], $this->user );
        Pages::publishMany( [$root->id], $this->user );
        Pages::save( $root->id, ['config' => $this->websiteConfig( 'Draft website' )], $this->user );

        $response = $this->get( '/blog?website=published' );
        $response->assertOk();
        $response->assertSee( 'title="Published website"', false );
        $response->assertDontSee( 'Draft website' );

        $response = $this->actingAs( $this->user )->get( '/blog?website=preview' );
        $response->assertOk();
        $response->assertSee( 'title="Draft website"', false );
        $response->assertDontSee( 'Published website' );
    }


    public function testLatestFindsChangedPath()
    {
        $page = PageView::where( 'tag', 'blog' )->firstOrFail();

        // Save with a new path (mimics admin panel which always sends domain)
        Pages::save(
            $page->id,
            ['path' => 'new-blog-path', 'domain' => $page->domain ?? ''],
            $this->user,
        );

        // Now try to access the page via the new path (as an editor would)
        $response = $this->actingAs( $this->user )->get( '/new-blog-path' );
        $response->assertStatus( 200 );
    }


    public function testEditorPreviewPrefersDraftWithUncachedDomain(): void
    {
        $page = PageView::where( 'tag', 'blog' )->firstOrFail();
        $other = PageView::where( 'path', 'hidden' )->firstOrFail();
        $other->variant->forceFill( ['latest_id' => null] )->saveQuietly(); // published page without versions

        // the cached domain list doesn't contain the new domain of the draft yet
        $this->actingAs( $this->user )->get( '/blog' )->assertOk();
        Pages::save( $page->id, ['path' => $other->path, 'domain' => 'new.tld', 'name' => 'Moved draft'], $this->user );

        $response = $this->actingAs( $this->user )->get( '/' . $other->path );
        $response->assertOk();
        $response->assertSee( '<title>' . $page->name . ' |', false );
    }


    public function testPublishingChangedRouteInvalidatesPreviousAndCurrentCompletePage(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $oldPath = $page->path;
        $newPath = 'renamed-page';

        $this->cache( $oldPath, 'old-route', $page->domain );
        $this->cache( $newPath, 'new-route', $page->domain );

        Pages::save( $page->id, ['path' => $newPath], $this->user );
        Pages::publishMany( [$page->id], $this->user );

        $this->assertNull( PageCache::response( $oldPath, $page->domain ) );
        $this->assertNull( PageCache::response( $newPath, $page->domain ) );
    }


    public function testDroppingPageInvalidatesCompleteSubtreeCache(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $parent = Pages::add( [
            'lang' => 'en', 'name' => 'Parent', 'title' => 'Parent', 'path' => 'cache-parent',
            'content' => [],
        ], $this->user, parent: (string) $root->id );
        $child = Pages::add( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'cache-child',
            'content' => [],
        ], $this->user, parent: (string) $parent->id );

        $this->cache( $parent, 'cached-parent' );
        $this->cache( $child, 'cached-child' );

        Pages::dropMany( [(string) $parent->id], $this->user );

        $this->assertNull( PageCache::response( $parent ) );
        $this->assertNull( PageCache::response( $child ) );
    }


    public function testLatestFindsChangedPathNoDomain()
    {
        $page = PageView::where( 'tag', 'article' )->firstOrFail();

        // Save with a new path (no domain in input — e.g. MCP tool)
        Pages::save(
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
        $page = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertEquals( 'mydomain.tld', $page->domain );

        // Admin panel save sends the page's domain
        Pages::save(
            $page->id,
            ['path' => 'new-home', 'domain' => $page->domain],
            $this->user,
        );

        // Without multidomain config, the route has no {domain} parameter,
        // so $domain defaults to '' in the controller
        $response = $this->actingAs( $this->user )->get( '/new-home' );
        $this->assertNotEquals( 404, $response->status() );
    }


    public function testLatestFindsChangedPathWithNewDomain()
    {
        $page = PageView::where( 'tag', 'article' )->firstOrFail();

        // populates the cached list of the domains used by the variants
        $this->actingAs( $this->user )->get( '/welcome-to-laravelcms' )->assertOk();

        Pages::save( $page->id, ['path' => 'new-domain-path', 'domain' => 'New.Example.com'], $this->user );

        $this->assertSame( 'new.example.com', PageView::find( $page->id )->latest?->data->domain );
        $this->actingAs( $this->user )->get( '/new-domain-path' )->assertOk();
    }


    public function testNewsPageUsesBlogLayout()
    {
        $page = PageView::where( 'tag', 'article' )->firstOrFail();
        $page->variant->update( ['tag' => '', 'type' => 'news'] );

        $response = $this->get( '/welcome-to-laravelcms' );

        $response->assertOk();
        $response->assertSee( 'layout-blog.css', false );
    }


    public function testLatestFindsExistingVersionWithoutDomain()
    {
        // Create a page with a version that has no domain in data (legacy/importer case)
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Test',
            'title' => 'Test Page',
            'path' => 'test-page',
            'status' => 1,
            'editor' => 'test',
        ] )->view;

        $version = $page->versions()->forceCreate([
            'data' => ['name' => 'Test', 'path' => 'test-page', 'status' => 1],
            'aux' => [],
            'published' => true,
            'editor' => 'test',
        ]);
        $page->variant->update( ['latest_id' => $version->id] );

        // Now save with a new path (no domain in input)
        Pages::save(
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
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Draft preview',
            'title' => 'Draft preview',
            'path' => 'draft-preview',
            'status' => 0,
            'editor' => 'test',
        ] )->view;
        $pageVersion = $page->versions()->forceCreate([
            'lang' => 'en',
            'data' => ['name' => 'Draft preview', 'path' => 'draft-preview', 'status' => 0],
            'aux' => ['content' => [
                ['id' => 'el1', 'type' => 'reference', 'group' => 'main', 'refid' => $element->id],
            ]],
            'published' => false,
            'editor' => 'test',
        ]);
        $page->variant->update( ['latest_id' => $pageVersion->id] );
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
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        Pages::add( [
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
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        Pages::add( [
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


    public function testHeroRendersButtons()
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        Pages::add( [
            'lang' => 'en',
            'name' => 'Hero buttons',
            'title' => 'Hero buttons',
            'path' => 'hero-buttons',
            'status' => 1,
            'content' => [[
                'id' => 'hero-buttons',
                'type' => 'hero',
                'group' => 'main',
                'data' => [
                    'title' => 'Hero buttons',
                    'buttons' => [
                        ['label' => 'First action', 'url' => 'https://example.com/first', 'url-rel' => 'nofollow'],
                        ['label' => 'Second action', 'url' => 'https://example.com/second'],
                        ['label' => 'Third action', 'url' => 'https://example.com/third'],
                    ],
                ],
            ]],
        ], $this->user, parent: $root->id );

        $response = $this->actingAs( $this->user )->get( '/hero-buttons' );

        $response->assertStatus( 200 );
        $response->assertSeeInOrder( ['First action', 'Second action', 'Third action'] );
        $response->assertSee( 'href="https://example.com/first" rel="nofollow"', false );
        $this->assertSame( 3, substr_count( (string) $response->getContent(), '<a class="btn"' ) );
    }


    public function testAnonymousCacheablePageHasNoCookies()
    {
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $node->variants()->create( [
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
        ] )->view;

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
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Stale',
            'title' => 'Stale',
            'path' => 'stale',
            'status' => 1,
            'cache' => 5,
            'editor' => 'test',
        ] )->view;
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
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
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
        $first = $method->invoke( null, 'a', 'b', 'c|d' );
        $second = $method->invoke( null, 'a', 'c', 'd' );

        $this->assertNotSame(
            $first,
            $method->invoke( null, 'a|b', 'c', 'd' ),
        );
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $first );
        $this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $second );
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

        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
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
            Permission::system( fn() => PageAccess::set( [$page->id], [] ) );
        } finally {
            $lock->release();
        }

        $this->assertLessThan( 750, ( hrtime( true ) - $start ) / 1_000_000 );
        $this->assertNull( PageCache::response( $page ) );
    }


    public function testAccessInvalidationDeletesWhileRenderLeaseIsHeld(): void
    {
        config( ['cms.theme.cache' => 'array', 'cms.theme.lock' => 1] );

        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $key = $this->cacheKey( $page );
        $this->cache( $page, 'public-html' );
        $store = Cache::store( config( 'cms.theme.cache', 'file' ) )->getStore();

        if( !$store instanceof LockProvider ) {
            $this->fail( 'Theme cache store must support locks' );
        }

        $lock = $store->lock( $key . ':render', 30 );
        $this->assertTrue( $lock->get() );

        try {
            Permission::system( fn() => PageAccess::set( [$page->id], [] ) );
            $this->assertNull( PageCache::response( $page ) );
            $this->get( '/hidden' )->assertRedirect( '/login' );
        } finally {
            $lock->release();
        }
    }


    public function testAccessInvalidationKeepsPagesSharingNavigationCached(): void
    {
        config( ['cms.theme.cache' => 'array'] );

        $changed = PageView::where( 'path', 'hidden' )->firstOrFail();
        $shared = PageView::where( 'path', 'blog' )->firstOrFail();
        $this->cache( $changed, 'changed-html' );
        $this->cache( $shared, 'shared-html' );

        Permission::system( fn() => PageAccess::set( [(string) $changed->id], [] ) );

        $this->assertNull( PageCache::response( $changed ) );
        $this->assertSame( 'shared-html', PageCache::response( $shared )?->getContent() );
    }


    public function testRestrictedPageRedirectsGuestAndAllowsPermission(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$page->id], ['frontend.member'] ) );

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


    public function testRestrictedPageUsesExtendedAccessWithoutRuleQuery(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [(string) $page->id], ['frontend.member'] ) );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $user->cmsperms = [];
        Access::extend( fn() => ['frontend.member'] );
        \Illuminate\Support\Facades\Gate::define( 'frontend.member', fn() => false );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs( $user )->get( '/hidden' );

        $response->assertOk();
        $this->assertStringContainsString( 'private', (string) $response->headers->get( 'Cache-Control' ) );

        foreach( DB::getQueryLog() as $query ) {
            $this->assertDoesNotMatchRegularExpression(
                '/^select \\* from [`"]cms_page_access[`"]/i',
                (string) $query['query'],
            );
        }
    }


    public function testRestrictedPageRenderSignsPrivateAssets(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $file = ( new File() )->forceFill( [
            'id' => 'private-file', 'disk' => 'private', 'path' => 'cms/test/private.pdf',
        ] );
        $url = null;
        Permission::system( fn() => PageAccess::set( [$page->id], ['frontend.member'] ) );
        View::composer( '*', function() use ( &$url, $file, $page ) {
            $url ??= cmsasset( $page, $file );
        } );

        $user = new \App\Models\User();
        $user->id = 44;
        $user->tenant_id = 'test';
        $user->cmsperms = [];
        \Illuminate\Support\Facades\Gate::define( 'frontend.member', fn() => true );

        $this->actingAs( $user )->get( '/hidden' )->assertOk();

        $this->assertIsString( $url );
        $this->assertStringContainsString( 'signature=', $url );
    }


    public function testRestrictedPageReturnsUnauthorizedForJsonGuest(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$page->id], ['frontend.member'] ) );

        $this->getJson( '/hidden' )->assertUnauthorized();
    }


    public function testRestrictedPageForbidsAuthenticatedUserWithoutPermission(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$page->id], ['frontend.member'] ) );

        $user = new \App\Models\User();
        $user->id = 43;
        $user->tenant_id = 'test';
        $user->cmsperms = [];
        \Illuminate\Support\Facades\Gate::define( 'frontend.member', fn() => false );

        $this->actingAs( $user )->get( '/hidden' )->assertForbidden();
    }


    public function testEditorFromAnotherTenantCannotBypassPageAccess(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$page->id], [] ) );
        $this->user->tenant_id = 'other';

        $this->actingAs( $this->user )->get( '/hidden' )->assertForbidden();
    }


    public function testRestrictedPageIsHiddenFromGuestNavigation(): void
    {
        $blog = PageView::where( 'path', 'blog' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$blog->id], [] ) );

        $response = $this->get( '/hidden' );

        $response->assertOk();
        $response->assertDontSee( 'href="http://localhost/blog"', false );
    }


    public function testRedirectUsesOneQuery(): void
    {
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $node->variants()->create( [
            'lang' => 'en',
            'name' => 'Redirect',
            'title' => 'Redirect',
            'path' => 'redirect',
            'to' => '/target',
            'status' => 1,
            'editor' => 'test',
        ] )->view;

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get( '/redirect' )
            ->assertRedirect( '/target' )
            ->assertStatus( Response::HTTP_MOVED_PERMANENTLY );

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
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Permission::system( fn() => PageAccess::set( [$page->id], [] ) );
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get( '/hidden' )->assertRedirect( '/login' );

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testUncachedPageUsesFullWebSession()
    {
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $node->variants()->create( [
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
        ] )->view;

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


    private function blogList(): PageView
    {
        $page = PageView::where( 'tag', 'blog' )->firstOrFail();
        $content = collect( (array) $page->content )
            ->map( fn( $item ) => (object) ( (array) $item + ['group' => 'main'] ) )
            ->all();

        $page->variant->update( ['content' => $content, 'type' => 'blog'] );

        return $page;
    }


    private function cacheKey( PageView|string $page, string $domain = '' ): string
    {
        $key = ( new \ReflectionMethod( PageCache::class, 'key' ) )->invoke( null, $page, $domain );

        if( !is_string( $key ) ) {
            $this->fail( 'Expected a string cache key' );
        }

        return $key;
    }


    private function cache( PageView|string $page, string $html, string $domain = '' ): void
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
        Cache::store( config( 'cms.theme.cache', 'file' ) )->put(
            $key,
            ['gzip' => base64_encode( (string) gzencode( $html, 6 ) ), 'freshUntil' => $expires->getTimestamp()],
            60,
        );
    }


    /**
     * @return array<string, object>
     */
    private function websiteConfig( string $title ): array
    {
        return ['website' => Validation::entry( 'website', ['title' => $title], 'config' )];
    }
}
