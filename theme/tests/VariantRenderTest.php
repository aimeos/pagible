<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Actions\Blog;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Navigation;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;


class VariantRenderTest extends ThemeTestAbstract
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

        config( ['cms.translate.fallback' => 'hide', 'app.fallback_locale' => 'en'] );
    }


    protected function tearDown(): void
    {
        config( ['cms.translate.fallback' => 'hide', 'cms.locales' => ['en', 'de'], 'app.fallback_locale' => 'en'] );
        parent::tearDown();
    }


    public function testRoutesVariant()
    {
        $this->variant( $this->blog(), 'de', 'blog-de' );

        $response = $this->get( '/blog-de' );
        $response->assertOk();
        $response->assertSee( '<html class="no-js" data-theme="light" lang="de" dir="ltr">', false );

        $this->get( '/blog' )->assertOk()->assertSee( 'lang="en"', false );
    }


    public function testUnpublishedVariantNotFound()
    {
        $blog = $this->blog();
        Resource::addVariant( $blog->id, 'de', $this->user );
        Resource::savePage( $blog->id, ['path' => 'blog-de'], $this->user, lang: 'de' );

        $this->get( '/blog-de' )->assertNotFound();
    }


    public function testTrashedVariantNotFound()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );
        Resource::dropVariant( $blog->id, 'de', $this->user );

        $this->get( '/blog-de' )->assertNotFound();
        $this->get( '/blog' )->assertOk()->assertDontSee( 'hreflang="de"', false );
    }


    public function testRtlLanguage()
    {
        config( ['cms.locales' => ['en', 'de', 'ar']] );

        $this->variant( $this->blog(), 'ar', 'blog-ar' );

        $response = $this->get( '/blog-ar' );
        $response->assertOk();
        $response->assertSee( 'lang="ar" dir="rtl"', false );
        $response->assertSee( 'aria-label="' . e( trans( 'Language', [], 'ar' ) ) . '"', false );
        $this->assertNotSame( 'Language', trans( 'Language', [], 'ar' ) );
    }


    public function testHreflang()
    {
        $this->get( '/blog' )->assertOk()->assertDontSee( 'hreflang=', false );

        $this->variant( $this->blog(), 'de', 'blog-de' );

        $response = $this->get( '/blog-de' );
        $response->assertOk();
        $response->assertSee( '<link rel="alternate" hreflang="de" href="' . url( 'blog-de' ) . '">', false );
        $response->assertSee( '<link rel="alternate" hreflang="en" href="' . url( 'blog' ) . '">', false );
        $response->assertSee( '<link rel="alternate" hreflang="x-default" href="' . url( 'blog' ) . '">', false );
    }


    public function testHreflangWithoutDefault()
    {
        config( ['app.fallback_locale' => 'fr'] );

        $this->variant( $this->blog(), 'de', 'blog-de' );

        $response = $this->get( '/blog' );
        $response->assertOk();
        $response->assertSee( 'hreflang="de"', false );
        $response->assertDontSee( 'hreflang="x-default"', false );
    }


    public function testHreflangPublishedOnly()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );
        config( ['cms.locales' => ['en', 'de', 'fr']] );
        Resource::addVariant( $blog->id, 'fr', $this->user );

        $response = $this->get( '/blog' );
        $response->assertSee( 'hreflang="de"', false );
        $response->assertDontSee( 'hreflang="fr"', false );
    }


    public function testBlogListsVariantsInPageLanguage()
    {
        $blog = $this->blog();
        $article = Page::where( 'tag', 'article' )->firstOrFail();
        $article->forceFill( ['type' => 'blog'] )->saveQuietly();

        $deBlog = $this->variant( $blog, 'de', 'blog-de' );
        $item = (object) ['data' => (object) ['order' => '-id', 'limit' => 10]];

        // no German article yet
        $this->assertCount( 0, ( new Blog() )( $this->request(), $deBlog, $item )->getCollection() );
        $this->assertCount( 1, ( new Blog() )( $this->request(), $blog, $item )->getCollection() );

        config( ['cms.translate.fallback' => 'source'] );
        $this->assertCount( 1, ( new Blog() )( $this->request(), $deBlog, $item )->getCollection() );
        config( ['cms.translate.fallback' => 'hide'] );

        $this->variant( $article, 'de', 'artikel', ['type' => 'blog'] );
        $result = ( new Blog() )( $this->request(), $deBlog, $item )->getCollection();

        $this->assertCount( 1, $result );
        $this->assertSame( 'de', $result->first()->lang );
        $this->assertSame( 'artikel', $result->first()->path );

        // only pages of the current domain
        $deBlog->domain = 'other.tld';
        $this->assertCount( 0, ( new Blog() )( $this->request(), $deBlog, $item )->getCollection() );
    }


    public function testNavigationFallback()
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $deRoot = $this->variant( $root, 'de', 'start' );

        $names = fn() => ( new Navigation( $deRoot, null ) )->items()->pluck( 'name' )->all();

        $this->assertNotContains( 'Blog', $names() );

        config( ['cms.translate.fallback' => 'source'] );
        $this->assertContains( 'Blog', $names() );

        config( ['cms.translate.fallback' => 'hide'] );
        $this->variant( $this->blog(), 'de', 'blog-de', ['name' => 'Blog DE'] );
        $this->assertContains( 'Blog DE', $names() );
    }


    public function testRobotsFromSourceRoot()
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $this->variant( $root, 'de', 'start' );

        $config = fn( $text ) => ['robots-txt' => ['type' => 'robots-txt', 'data' => ['text' => $text], 'files' => []]];
        PageVariant::where( 'page_id', $root->id )->where( 'lang', 'en' )->firstOrFail()->forceFill( ['config' => $config( "User-agent: *\nDisallow: /en" )] )->saveQuietly();
        PageVariant::where( 'page_id', $root->id )->where( 'lang', 'de' )->firstOrFail()->forceFill( ['config' => $config( "User-agent: *\nDisallow: /de" )] )->saveQuietly();

        $response = $this->get( '/robots.txt' );
        $response->assertOk();
        $response->assertSee( 'Disallow: /en' );
        $response->assertDontSee( 'Disallow: /de' );
    }


    public function testSitemapListsVariants()
    {
        $this->variant( $this->blog(), 'de', 'blog-de' );
        Resource::addVariant( $this->blog()->id, 'fr', $this->user );

        $content = $this->get( '/sitemap.xml' )->assertOk()->streamedContent();

        $this->assertStringContainsString( '<loc><![CDATA[' . url( 'blog' ) . ']]></loc>', $content );
        $this->assertStringContainsString( '<loc><![CDATA[' . url( 'blog-de' ) . ']]></loc>', $content );
        $this->assertStringNotContainsString( 'blog-fr', $content );
    }


    protected function blog() : Page
    {
        return Page::where( 'tag', 'blog' )->firstOrFail();
    }


    protected function request() : Request
    {
        $request = Request::create( '/blog' );
        $request->setUserResolver( fn() => null );

        return $request;
    }


    protected function variant( Page $page, string $lang, string $path, array $input = [] ) : Page
    {
        Resource::addVariant( $page->id, $lang, $this->user );
        Resource::savePage( $page->id, ['path' => $path, 'status' => 1] + $input, $this->user, lang: $lang );
        Publication::publish( Page::class, [$page->id], $this->user, lang: $lang );

        return Page::language( $lang )->findOrFail( $page->id );
    }
}
