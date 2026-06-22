<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Actions\Blog;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
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
        Tenancy::$callback = fn() => 'demo';

        $blog = Page::where( 'tag', 'blog' )->firstOrFail();
        $article = Page::where( 'tag', 'article' )->firstOrFail();

        $fileId = $article->files()->pluck( 'cms_files.id' )->first();

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
    }
}
