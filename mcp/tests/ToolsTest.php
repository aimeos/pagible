<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Http;


class ToolsTest extends McpTestAbstract
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

        $this->user = \App\Models\User::create([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    // ── Discovery & Configuration ──────────────────────────────────────

    public function testGetLocales()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetLocales::class );

        $response->assertOk()->assertStructuredContent( ['locales' => ['en', 'de']] );
    }


    public function testGetSchemas()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetSchemas::class );

        $response->assertOk()->assertSee( ['heading'] );
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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

        Analytics::shouldReceive('driver->stats')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn(['views' => [['key' => '2026-03-01', 'value' => 100]]]);

        Analytics::shouldReceive('search')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn(['impressions' => 50, 'clicks' => 10]);

        Analytics::shouldReceive('queries')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn([['key' => 'test query', 'clicks' => 5]]);

        Analytics::shouldReceive('pagespeed')
            ->once()
            ->with('https://example.com/blog')
            ->andReturn([['key' => 'time_to_first_byte', 'value' => 250]]);

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetPageMetrics::class, [
            'url' => 'https://example.com/blog',
        ] );

        $response->assertOk()->assertSee( ['views', 'pagespeed'] );
    }


    public function testListPages()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListPages::class, [
            'lang' => 'en',
        ] );

        $response->assertOk()->assertSee( ['Home'] );
    }


    public function testListPagesFilterStatus()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListPages::class, [
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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\MovePage::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    // ── Read Elements ──────────────────────────────────────────────────

    public function testGetElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer', 'footer', 'latest_version'] );
    }


    public function testGetElementNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testListElements()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListElements::class );

        $response->assertOk()->assertSee( ['Shared footer', 'footer'] );
    }


    public function testListElementsFilterType()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListElements::class, [
            'type' => 'footer',
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
    }


    public function testSearchElements()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        sleep( 5 ); // Wait for SQL Server to update fulltext index

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class, [
            'term' => 'footer',
        ] );
        $response->assertOk()->assertSee( ['Shared footer'] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchElements::class, [
            'type' => 'footer',
        ] );
        $response->assertOk()->assertSee( ['Shared footer'] );
    }


    // ── Write Elements ─────────────────────────────────────────────────

    public function testAddElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddElement::class, [
            'type' => 'heading',
            'name' => 'Test heading',
            'lang' => 'en',
            'data' => ['title' => 'Hello', 'level' => '2'],
        ] );

        $response->assertOk()->assertSee( ['Test heading', 'heading'] );
    }


    public function testSaveElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveElement::class, [
            'id' => $element->id,
            'name' => 'Updated footer',
        ] );

        $response->assertOk()->assertSee( ['Updated footer'] );
    }


    public function testSaveElementNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testPublishElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishElement::class, [
            'id' => [$element->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishElementScheduled()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishElement::class, [
            'id' => [$element->id],
            'at' => '2099-06-01 00:00:00',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-06-01'] );
    }


    public function testDropElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
        $this->assertSoftDeleted( 'cms_elements', ['id' => $element->id] );
    }


    public function testDropElementNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropElement::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testRestoreElement()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();
        $element->delete();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['Shared footer'] );
        $this->assertNull( Element::find( $element->id )->deleted_at );
    }


    public function testRestoreElementNotDeleted()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $element = Element::where( 'name', 'Shared footer' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreElement::class, [
            'id' => $element->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }


    // ── Read Files ─────────────────────────────────────────────────────

    public function testGetFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );
    }


    public function testGetFileNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testListFiles()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListFiles::class );

        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );
    }


    public function testListFilesFilterMime()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\ListFiles::class, [
            'mime' => 'image/tiff',
        ] );

        $response->assertOk()->assertSee( ['Test file', 'image/tiff'] );
    }


    public function testSearchFiles()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        sleep( 5 ); // Wait for SQL Server to update fulltext index

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class, [
            'term' => 'Test image',
        ] );
        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class, [
            'term' => 'Test',
            'mime' => 'image/tiff',
        ] );
        $response->assertOk()->assertSee( ['Test file', 'image/tiff'] );
    }


    // ── Write Files ────────────────────────────────────────────────────

    public function testAddFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        Http::fake([
            'https://example.com/*' => Http::response( 'plain text content', 200 ),
        ]);

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'https://example.com/document.txt',
            'name' => 'New test file',
            'lang' => 'en',
            'description' => ['en' => 'A test file'],
        ] );

        $response->assertOk()->assertSee( ['New test file'] );
    }


    public function testAddFileInvalidUrl()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'ftp://invalid.com/file.jpg',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSaveFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'name' => 'Renamed image',
        ] );

        $response->assertOk()->assertSee( ['Renamed image'] );
    }


    public function testSaveFileNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testPublishFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishFile::class, [
            'id' => [$file->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishFileScheduled()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishFile::class, [
            'id' => [$file->id],
            'at' => '2099-12-31 23:59:59',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-12-31'] );
    }


    public function testDropFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image'] );
        $this->assertSoftDeleted( 'cms_files', ['id' => $file->id] );
    }


    public function testDropFileNotFound()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testRestoreFile()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();
        $file->delete();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image'] );
        $this->assertNull( File::find( $file->id )->deleted_at );
    }


    public function testRestoreFileNotDeleted()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }


    // ── AI Tools ───────────────────────────────────────────────────────

    public function testTranslateContent()
    {
        config(['cms.ai.translate.api_key' => 'test-key']);

        $texts = ['Hello', 'World'];
        $expected = ['Hallo', 'Welt'];

        $response = \Aimeos\Prisma\Responses\TextResponse::fromTexts( $expected );
        \Aimeos\Prisma\Prisma::fake( [$response] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\TranslateContent::class, [
            'texts' => ['Hello World'],
            'to' => 'de',
        ] );

        $response->assertOk()->assertSee( ['translations'] );
    }
}
