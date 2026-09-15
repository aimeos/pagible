<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Actions\Blog;
use Aimeos\Cms\Actions\News;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;


class BlogActionTest extends ThemeTestAbstract
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


    public function testEditorPreviewLoadsImageFromDraft()
    {
        $blog = Page::where( 'tag', 'blog' )->firstOrFail();
        $article = Page::where( 'tag', 'article' )->firstOrFail();

        $fileId = $article->files()->pluck( 'cms_files.id' )->first();
        File::whereKey( $fileId )->update( ['disk' => 'private'] );

        // The article is an already-published blog page (page columns reflect the published
        // state; a draft save only writes a new version, not the page row).
        $article->forceFill( ['type' => 'blog'] )->saveQuietly();

        // Re-save the article as an unpublished draft. Validation::page populates the
        // per-element "files" list, which lands in the new latest version's aux.content.
        $content = [
            ['type' => 'article', 'data' => [
                'title' => 'Welcome to Laravel CMS',
                'text' => 'A new light-weight Laravel CMS is here!',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ]],
        ];
        Resource::savePage( $article->id, ['type' => 'blog', 'content' => $content], $this->user );

        $request = Request::create( '/blog' );
        $request->setUserResolver( fn() => $this->user );

        $result = ( new Blog() )( $request, $blog, $this->item( $blog ) );
        $page = $result->getCollection()->firstWhere( 'id', $article->id );

        // Without latest_id in the action's select the latest relation can't eager-load,
        // so the draft content (with its image) is never read and the image is lost.
        $this->assertNotNull( $page );
        $this->assertNotNull( $page->latest );
        $this->assertTrue( $page->files->isNotEmpty() );
        $this->assertSame( 'private', $page->files->first()->disk );
    }


    public function testNewsLoadsDraftPagesByTypeWithoutArticleTag()
    {
        $blog = Page::where( 'tag', 'blog' )->firstOrFail();
        $article = Page::where( 'tag', 'article' )->firstOrFail();
        $article->forceFill( ['tag' => '', 'type' => 'blog'] )->saveQuietly();

        Resource::savePage( $article->id, ['type' => 'news'], $this->user );

        $request = Request::create( '/news' );
        $request->setUserResolver( fn() => $this->user );

        $item = $this->item( $blog );
        $result = ( new News() )( $request, $blog, $item );

        $this->assertSame( 'blog', $article->fresh()->type );
        $this->assertSame( [$article->id], $result->getCollection()->pluck( 'id' )->all() );
        $this->assertArrayNotHasKey( 'type', $result->getCollection()->first()->getAttributes() );

        $html = view( 'cms::news', ['action' => $result, 'data' => $item->data, 'page' => $blog] )->render();

        $this->assertStringContainsString( $article->title, $html );
        $this->assertStringNotContainsString( 'application/ld+json', $html );
    }


    public function testStoresCurrentPaginationPageOnRequest()
    {
        $blog = Page::where( 'tag', 'blog' )->firstOrFail();
        $request = Request::create( '/blog', 'GET', ['p' => 2] );
        $request->setUserResolver( fn() => null );
        $this->app->instance( 'request', $request );

        $result = ( new Blog() )( $request, $blog, $this->item( $blog ) );

        $this->assertSame( 2, $result->currentPage() );
        $this->assertSame( 2, $request->attributes->get( 'cms.pagination' ) );
    }


    protected function item( Page $page ) : object
    {
        return (object) ['data' => (object) [
            'order' => '-id',
            'limit' => 10,
            'parent-page' => (object) ['value' => $page->id],
        ]];
    }
}
