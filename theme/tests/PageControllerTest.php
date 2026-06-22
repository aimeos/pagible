<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


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
        $this->user->cmsperms = ['admin'];
    }


    public function testLatestFindsChangedPath()
    {
        Tenancy::$callback = fn() => 'demo';

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


    public function testLatestFindsChangedPathNoDomain()
    {
        Tenancy::$callback = fn() => 'demo';

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
        Tenancy::$callback = fn() => 'demo';

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
        Tenancy::$callback = fn() => 'demo';

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
        Tenancy::$callback = fn() => 'demo';

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


    public function testAnonymousCacheablePageHasNoCookies()
    {
        Tenancy::$callback = fn() => 'demo';

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
    }


    public function testUncachedPageUsesFullWebSession()
    {
        Tenancy::$callback = fn() => 'demo';

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
        Tenancy::$callback = fn() => 'demo';

        // The token endpoint must still start a session on demand so the lazy
        // CSRF flow works for cached pages.
        $response = $this->get( '/cmsapi/csrf' );

        $response->assertStatus( 200 );
        $response->assertJsonStructure( ['token'] );
        $response->assertCookie( config( 'session.cookie' ) );
    }
}
