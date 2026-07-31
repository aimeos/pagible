<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Actions\Blog;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;


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
        Resource::savePage( $article->id, ['content' => $content], $this->user );

        $request = Request::create( '/blog' );
        $request->setUserResolver( fn() => $this->user );

        $item = (object) ['data' => (object) [
            'order' => '-id',
            'limit' => 10,
            'parent-page' => (object) ['value' => $blog->id],
        ]];

        $result = ( new Blog() )( $request, $blog, $item );
        $page = $result->getCollection()->firstWhere( 'id', $article->id );

        // Without latest_id in the action's select the latest relation can't eager-load,
        // so the draft content (with its image) is never read and the image is lost.
        $this->assertNotNull( $page );
        $this->assertNotNull( $page->latest );
        $this->assertTrue( $page->files->isNotEmpty() );
        $this->assertSame( 'private', $page->files->first()->disk );
    }


    public function testFrontendAccessFiltersArticles()
    {
        Access::using( fn() => ['frontend.member'] );
        Gate::define( 'frontend.member', fn() => true );

        $blog = Page::where( 'tag', 'blog' )->firstOrFail();
        $article = Page::where( 'tag', 'article' )->firstOrFail();
        $article->forceFill( ['type' => 'blog'] )->saveQuietly();
        PageAccess::set( [$article->id], ['frontend.member'] );

        $item = (object) ['data' => (object) [
            'order' => '-id',
            'limit' => 10,
            'parent-page' => (object) ['value' => $blog->id],
        ]];
        $list = function( ?\App\Models\User $user ) use ( $blog, $item ) {
            $request = Request::create( '/blog' );
            $request->setUserResolver( fn() => $user );

            return ( new Blog() )( $request, $blog, $item )->getCollection()->pluck( 'id' );
        };

        $user = new \App\Models\User( ['cmsperms' => []] );
        $user->id = 42;
        $user->tenant_id = 'test';

        $this->assertNotContains( $article->id, $list( null ) );
        $this->assertContains( $article->id, $list( $user ) );
    }
}
