<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;


class PageToolsTest extends McpTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        Access::using( fn() => ['member', 'staff'] );

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    // ── Read Pages ─────────────────────────────────────────────────────

    public function testGetPage()
    {
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['Home', 'Home | Laravel CMS'] );
    }


    public function testGetPageIncludesImmediateAccess()
    {
        $page = Page::where( 'name', 'Home' )->firstOrFail();
        PageAccess::set( [$page->id], [] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'access', [] )
            ->where( 'restricted', true )
            ->etc()
        );
    }


    public function testGetPageProtectsImmediateAccessValues()
    {
        $page = Page::where( 'name', 'Home' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $this->user->cmsperms = ['page:view'];

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'restricted', true )
            ->missing( 'access' )
            ->etc()
        );
    }


    public function testGetPageByPath()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'path' => 'blog',
        ] );

        $response->assertOk()->assertSee( ['Blog', 'Blog | Laravel CMS'] );
    }


    public function testGetPageByEmptyPath()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'path' => '',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testGetPageMissingParams()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [] );

        $response->assertHasErrors( ['either an ID or a path'] );
    }


    public function testGetPageNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testGetPageTree()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageTree::class, [
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testGetPageHistory()
    {
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageHistory::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['page_id', 'versions', 'seeder'] );
    }


    public function testGetPageMetrics()
    {
        \Aimeos\AnalyticsBridge\Facades\Analytics::shouldReceive('driver->stats')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn(['views' => [['key' => '2026-03-01', 'value' => 100]]]);

        \Aimeos\AnalyticsBridge\Facades\Analytics::shouldReceive('search')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn(['impressions' => 50, 'clicks' => 10]);

        \Aimeos\AnalyticsBridge\Facades\Analytics::shouldReceive('queries')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn([['key' => 'test query', 'clicks' => 5]]);

        \Aimeos\AnalyticsBridge\Facades\Analytics::shouldReceive('pagespeed')
            ->once()
            ->with('https://example.com/blog')
            ->andReturn([['key' => 'time_to_first_byte', 'value' => 250]]);

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageMetrics::class, [
            'url' => 'https://example.com/blog',
        ] );

        $response->assertOk()->assertSee( ['views', 'pagespeed'] );
    }


    public function testSearchPagesNoTerm()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testSearchPagesFilterStatus()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'status' => 0,
        ] );

        $response->assertOk()->assertSee( ['Disabled'] );
    }


    public function testSearchPages()
    {
        if( DB::connection( config( 'cms.db' ) )->getDriverName() === 'sqlsrv' ) {
            sleep( 5 );
        }

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'lang' => 'en',
            'term' => 'blog',
        ] );

        $response->assertOk()->assertSee( [
            'en',
            'blog',
            'Blog | Laravel CMS',
        ] );
    }


    // ── Write Pages ────────────────────────────────────────────────────

    public function testAddPage()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Test page',
            'title' => 'A Test Page',
            'content' => [
                ['type' => 'heading', 'data' => ['title' => 'Hello World', 'level' => '1']],
                ['type' => 'text', 'data' => ['text' => 'This is a test page.']],
            ],
            'meta' => $this->meta( 'A test page for unit testing' ),
        ] );

        $response->assertOk()->assertSee( [
            'en',
            'Test page',
            'A Test Page',
        ] );

        // the created page's id must be part of the response (Page::$visible omits it)
        $page = Page::where( 'name', 'Test page' )->first();
        $this->assertNotNull( $page );
        $response->assertSee( [$page->id] );

        $entry = $page->latest->aux->meta->{'meta-tags'};
        $this->assertEquals( 'meta-tags', $entry->type );
        $this->assertEquals( [], $entry->files );
        $this->assertObjectNotHasProperty( 'id', $entry );
    }


    public function testAddPagePreservesContentOrderWithoutIds()
    {
        // Interleave elements that carry an explicit id with id-less ones. The id-less
        // elements must keep their position and not get pushed to the end by validate()
        // rebuilding the content.*.id wildcard array.
        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Ordered page',
            'title' => 'An Ordered Page',
            'content' => [
                ['id' => 'el-a', 'type' => 'heading', 'data' => ['title' => 'First', 'level' => '1']],
                ['type' => 'text', 'data' => ['text' => 'Second']],
                ['id' => 'el-c', 'type' => 'heading', 'data' => ['title' => 'Third', 'level' => '2']],
                ['type' => 'text', 'data' => ['text' => 'Fourth']],
            ],
            'meta' => $this->meta( 'An ordered page for testing' ),
        ] );

        $page = Page::where( 'name', 'Ordered page' )->with( 'latest' )->first();
        $content = (array) ( $page->latest->aux->content ?? [] );
        $labels = array_map( fn( $el ) => $el->data->title ?? $el->data->text ?? null, $content );

        $this->assertSame( ['First', 'Second', 'Third', 'Fourth'], $labels );
    }


    public function testAddPageWithParent()
    {
        $parent = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Child page',
            'title' => 'A Child Page',
            'content' => [
                ['type' => 'text', 'data' => ['text' => 'Child content.']],
            ],
            'meta' => $this->meta( 'A child page for testing' ),
            'parent_id' => $parent->id,
        ] );

        $response->assertOk()->assertSee( ['Child page'] );
    }


    public function testAddPageWithMetaAndConfig()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Full page',
            'title' => 'A Full Page',
            'tag' => 'nav-start',
            'status' => 1,
            'cache' => 10,
            'content' => [
                ['type' => 'text', 'group' => 'main', 'data' => ['text' => 'Content']],
            ],
            'meta' => $this->meta( 'SEO description' ),
        ] );

        $response->assertOk()->assertSee( ['Full page', 'nav-start'] );
    }


    public function testAddPageMissingMetaDescription()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'No meta page',
            'title' => 'No Meta Page',
            'content' => [
                ['type' => 'text', 'data' => ['text' => 'Missing meta description.']],
            ],
        ] );

        $response->assertHasErrors( ['meta'] );
    }


    public function testAddPageInvalidContentType()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Bad page',
            'title' => 'Bad Page',
            'content' => [
                ['type' => 'nonexistent', 'data' => ['text' => 'fail']],
            ],
            'meta' => $this->meta( 'A bad page test' ),
        ] );

        $response->assertHasErrors( ['Unknown'] );
    }


    public function testSavePage()
    {
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'name' => 'Updated Home',
            'title' => 'Updated Title',
        ] );

        $response->assertOk()->assertSee( ['Updated Home'] );
    }


    public function testSavePageWithReference()
    {
        $page = Page::where( 'name', 'Home' )->first();
        $element = \Aimeos\Cms\Models\Element::first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'content' => [
                ['type' => 'heading', 'group' => 'main', 'data' => ['title' => 'With reference', 'level' => '2']],
                ['type' => 'reference', 'group' => 'footer', 'refid' => $element->id],
            ],
        ] );

        // reference element (data-less, refid only) must round-trip
        $response->assertOk()->assertSee( [$element->id, 'reference'] );
    }


    public function testSavePagePreservesContentOrderWithoutIds()
    {
        $page = Page::where( 'name', 'Home' )->first();

        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'content' => [
                ['id' => 'el-a', 'type' => 'heading', 'data' => ['title' => 'First', 'level' => '1']],
                ['type' => 'text', 'data' => ['text' => 'Second']],
                ['id' => 'el-c', 'type' => 'heading', 'data' => ['title' => 'Third', 'level' => '2']],
                ['type' => 'text', 'data' => ['text' => 'Fourth']],
            ],
        ] );

        $page = Page::where( 'name', 'Home' )->with( 'latest' )->first();
        $content = (array) ( $page->latest->aux->content ?? [] );
        $labels = array_map( fn( $el ) => $el->data->title ?? $el->data->text ?? null, $content );

        $this->assertSame( ['First', 'Second', 'Third', 'Fourth'], $labels );
    }


    public function testSavePageNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'latest_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSavePageRequiresLatestId()
    {
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'name' => 'No token',
        ] );

        $response->assertHasErrors( ['latest_id'] );
    }


    public function testSetPageAccess()
    {
        $page = Page::where( 'name', 'Hidden' )->firstOrFail();
        $tool = fn( ?array $access ) => CmsServer::actingAs($this->user)->tool(
            \Aimeos\Cms\Tools\SetPageAccess::class,
            ['id' => [$page->id], 'access' => $access],
        );

        $tool( [] )->assertOk()->assertStructuredContent( ['updated' => 1] );
        $this->assertSame( [''], PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all() );

        $tool( ['member'] )->assertOk()->assertStructuredContent( ['updated' => 1] );
        $this->assertSame( ['member'], PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all() );

        $tool( null )->assertOk()->assertStructuredContent( ['updated' => 1] );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testSetPageAccessRequiresAccessViewPermission()
    {
        $page = Page::where( 'name', 'Hidden' )->firstOrFail();
        $this->user->cmsperms = ['page:publish'];

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SetPageAccess::class, [
            'id' => [$page->id],
            'access' => null,
        ] );

        $response->assertHasErrors( ['Tool [set-page-access] not found.'] );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testSetPageAccessRequiresExplicitAccess()
    {
        $page = Page::where( 'name', 'Hidden' )->firstOrFail();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SetPageAccess::class, [
            'id' => [$page->id],
        ] );

        $response->assertHasErrors( ['access'] );
    }


    public function testPublishPage()
    {
        $page = Page::where( 'name', 'Hidden' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishPageMultiple()
    {
        $pages = Page::whereIn( 'name', ['Home', 'Blog'] )->pluck( 'id' )->all();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => $pages,
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishPageScheduled()
    {
        $page = Page::where( 'name', 'Hidden' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
            'at' => '2099-01-01 00:00:00',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-01-01'] );
    }


    public function testDropPage()
    {
        $page = Page::where( 'name', 'Dev' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropPage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['Dev'] );
        $this->assertSoftDeleted( 'cms_pages', ['id' => $page->id] );
    }


    public function testDropPageNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropPage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testRestorePage()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        $page->delete();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestorePage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['Dev'] );
        $this->assertNull( Page::find( $page->id )->deleted_at );
    }


    public function testRestorePageNotDeleted()
    {
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestorePage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }


    public function testMovePage()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        $parent = Page::where( 'name', 'Blog' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\MovePage::class, [
            'id' => $page->id,
            'parent_id' => $parent->id,
        ] );

        $response->assertOk()->assertSee( ['Dev'] );
    }


    public function testMovePageNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\MovePage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    /**
     * Returns canonical metadata for MCP page writes.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function meta( string $description ) : array
    {
        return [
            'meta-tags' => [
                'type' => 'meta-tags',
                'data' => ['description' => $description],
                'files' => [],
            ],
        ];
    }
}
