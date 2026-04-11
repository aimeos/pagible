<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;


class PageToolsTest extends McpTestAbstract
{
    use DatabaseTruncation;

    protected $connectionsToTransact = [];


    protected function beforeTruncatingDatabase(): void
    {
        // In-memory SQLite databases don't persist across test classes
        RefreshDatabaseState::$migrated = false;
    }


    protected function setUp(): void
    {
        parent::setUp();

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
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['Home', 'Home | Laravel CMS'] );
    }


    public function testGetPageByPath()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'path' => 'blog',
        ] );

        $response->assertOk()->assertSee( ['Blog', 'Blog | Laravel CMS'] );
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
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageTree::class, [
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testGetPageHistory()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
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
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testSearchPagesFilterStatus()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'status' => 0,
        ] );

        $response->assertOk()->assertSee( ['Disabled'] );
    }


    public function testSearchPages()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        sleep( 5 ); // Wait for SQL Server to update fulltext index

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
            'meta' => [
                'meta-tags' => ['description' => 'A test page for unit testing'],
            ],
        ] );

        $response->assertOk()->assertSee( [
            'en',
            'Test page',
            'A Test Page',
        ] );
    }


    public function testAddPageWithParent()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $parent = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Child page',
            'title' => 'A Child Page',
            'content' => [
                ['type' => 'text', 'data' => ['text' => 'Child content.']],
            ],
            'meta' => [
                'meta-tags' => ['description' => 'A child page for testing'],
            ],
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
            'meta' => [
                'meta-tags' => ['description' => 'SEO description'],
            ],
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
        $this->expectException( \InvalidArgumentException::class );

        CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Bad page',
            'title' => 'Bad Page',
            'content' => [
                ['type' => 'nonexistent', 'data' => ['text' => 'fail']],
            ],
            'meta' => [
                'meta-tags' => ['description' => 'A bad page test'],
            ],
        ] );
    }


    public function testSavePage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'name' => 'Updated Home',
            'title' => 'Updated Title',
        ] );

        $response->assertOk()->assertSee( ['Updated Home'] );
    }


    public function testSavePageNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testPublishPage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $page = Page::where( 'name', 'Hidden' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishPageMultiple()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $pages = Page::whereIn( 'name', ['Home', 'Blog'] )->pluck( 'id' )->all();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => $pages,
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishPageScheduled()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $page = Page::where( 'name', 'Hidden' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishPage::class, [
            'id' => [$page->id],
            'at' => '2099-01-01 00:00:00',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-01-01'] );
    }


    public function testDropPage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
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
        $this->seed( \Database\Seeders\CmsSeeder::class );
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
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $page = Page::where( 'name', 'Home' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestorePage::class, [
            'id' => $page->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }


    public function testMovePage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
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
}
