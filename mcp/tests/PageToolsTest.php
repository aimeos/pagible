<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageVariant;
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


    public function testPageMutationToolsOnlyRequireTheirActionPermission()
    {
        $user = new \App\Models\User([
            'cmsperms' => ['page:move', 'page:save', 'page:drop', 'page:keep', 'page:publish'],
        ]);

        foreach( [
            \Aimeos\Cms\Tools\MovePage::class,
            \Aimeos\Cms\Tools\SavePage::class,
            \Aimeos\Cms\Tools\DropPage::class,
            \Aimeos\Cms\Tools\RestorePage::class,
            \Aimeos\Cms\Tools\PublishPage::class,
        ] as $tool ) {
            CmsServer::actingAs( $user )->tool( $tool )
                ->assertDontSee( 'not found' );
        }
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
        $this->user->cmsperms = ['access:view', 'page:view'];

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

        $response->assertOk()->assertStructuredContent( ['error' => 'You must specify either an ID or a path.'] );
    }


    public function testGetPageMissingParamsWithoutDebug()
    {
        config( ['app.debug' => false] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [] );

        $response->assertOk()->assertStructuredContent( ['error' => 'You must specify either an ID or a path.'] );
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


    public function testGetPageTreeReflectsSavedDraft()
    {
        $page = Page::where( 'name', 'Home' )->firstOrFail();
        $previous = $page->latest_id;

        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $previous,
            'title' => 'Updated tree title',
        ] )->assertOk();

        $page->refresh();

        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageTree::class, [
            'node_id' => $page->id,
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'tree.0.id', $page->id )
            ->where( 'tree.0.latest_id', $page->latest_id )
            ->where( 'tree.0.title', 'Updated tree title' )
            ->where( 'tree.0.published', false )
            ->where( 'tree.0.publish_at', null )
            ->etc()
        );

        $this->assertNotSame( $previous, $page->latest_id );
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

        $response->assertOk()->assertStructuredContent( ['error' => 'Unknown content type "nonexistent"'] );
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
        $this->user = new \App\Models\User( ['cmsperms' => ['page:access']] );
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


    public function testSetPageAccessRequiresPageAccessPermission()
    {
        $page = Page::where( 'name', 'Hidden' )->firstOrFail();
        $this->user->cmsperms = ['page:publish', 'access:view'];

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


    public function testPublishPageSkipsPublishedSchedule()
    {
        $page = Page::where( 'name', 'Hidden' )->firstOrFail();
        $page->latest()->update( ['published' => true] );
        $publishAt = $page->latest()->value( 'publish_at' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
            'at' => '2099-01-01 00:00:00',
        ] );

        $response->assertOk()
            ->assertSee( ['skipped', 'Already published'] )
            ->assertDontSee( ['scheduled_at'] );
        $this->assertSame( $publishAt, $page->latest()->value( 'publish_at' ) );
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
    public function testGetPageLanguage()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['name' => 'Startseite'], $this->user, lang: 'de' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
            'lang' => 'de',
        ] );

        $response->assertOk()->assertSee( ['Startseite', 'variants', 'stale'] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
            'lang' => 'fr',
        ] );

        $response->assertSee( ['not found'] );
    }


    public function testGetPageHistoryLanguage()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['name' => 'Startseite'], $this->user, lang: 'de' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageHistory::class, [
            'id' => $page->id,
            'lang' => 'de',
        ] );

        $response->assertOk()->assertSee( ['versions', 'Startseite'] );
    }


    public function testSearchPagesLanguage()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['name' => 'Startseite'], $this->user, lang: 'de' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'lang' => 'de',
        ] );

        $response->assertOk()->assertSee( [$page->id] );
    }


    public function testAddPageDefaultLanguage()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'name' => 'No language',
            'title' => 'No language',
            'content' => [['type' => 'text', 'data' => ['text' => 'Default language']]],
            'meta' => $this->meta( 'A page without language' ),
        ] );

        $response->assertOk();

        $page = Page::where( 'name', 'No language' )->firstOrFail();
        $this->assertEquals( config( 'app.locale' ), $page->lang );
        $this->assertEquals( config( 'app.locale' ), $page->source );
    }


    public function testCopyPage()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\CopyPage::class, [
            'id' => $page->id,
            'parent_id' => $page->parent_id,
        ] );

        $response->assertOk();

        $copy = Page::where( 'name', 'Dev' )->where( 'id', '!=', $page->id )->firstOrFail();
        $response->assertSee( [$copy->id] );

        $this->assertEquals( $page->parent_id, $copy->parent_id );
        $this->assertEquals(
            PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'hashes' ),
            PageVariant::where( 'page_id', $copy->id )->where( 'lang', 'de' )->value( 'hashes' )
        );
    }


    public function testCopyPageRequiresAddPermission()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        $user = new \App\Models\User( ['cmsperms' => ['page:view', 'page:save']] );

        CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\CopyPage::class, ['id' => $page->id] )
            ->assertHasErrors();
    }


    public function testSavePageLanguage()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        $de = Page::language( 'de' )->findOrFail( $page->id );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'lang' => 'de',
            'latest_id' => $de->latest_id,
            'name' => 'Startseite',
        ] );

        $response->assertOk()->assertSee( ['Startseite'] );
        $this->assertEquals( 'Startseite', Page::language( 'de' )->findOrFail( $page->id )->latest->data->name );
        $this->assertNotEquals( 'Startseite', Page::findOrFail( $page->id )->latest->data->name );
    }


    public function testSavePageSource()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        $de = Page::language( 'de' )->findOrFail( $page->id );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'lang' => 'de',
            'latest_id' => $de->latest_id,
            'source' => 'de',
        ] );

        $response->assertOk();
        $this->assertEquals( 'de', Page::findOrFail( $page->id )->source );
        $this->assertEquals( 'de', Page::findOrFail( $page->id )->lang );
    }


    public function testDropRestorePageLanguage()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropPage::class, [
            'id' => $page->id,
            'lang' => 'de',
        ] );

        $response->assertOk()->assertSee( [$page->id] );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( Page::find( $page->id ) );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestorePage::class, [
            'id' => $page->id,
            'lang' => 'de',
        ] );

        $response->assertOk()->assertSee( [$page->id] );
        $this->assertNotNull( Page::language( 'de' )->find( $page->id ) );
    }


    public function testDropPageSourceLanguage()
    {
        $page = Page::where( 'name', 'Dev' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );

        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropPage::class, [
            'id' => $page->id,
            'lang' => $page->lang,
        ] )->assertOk()->assertSee( ['source language'] );

        $this->assertNotNull( Page::find( $page->id ) );
    }


    public function testPublishPageLanguage()
    {
        $page = Page::where( 'name', 'Home' )->first();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['name' => 'Startseite'], $this->user, lang: 'de' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
            'lang' => 'de',
        ] );

        $response->assertOk();
        $this->assertEquals( 'Startseite', Page::language( 'de' )->findOrFail( $page->id )->name );
        $this->assertEquals( 'Home', Page::findOrFail( $page->id )->name );
    }


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
