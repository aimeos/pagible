<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelJsonApi\Testing\MakesJsonApiRequests;


class JsonapiVariantTest extends JsonapiTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesJsonApiRequests;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User();
        $this->user->name = 'Test';
        $this->user->email = 'test@example.com';
        $this->user->tenant_id = 'test';
        $this->user->cmsperms = ['admin'];

        config( ['cms.translate.fallback' => 'hide'] );
    }


    protected function tearDown(): void
    {
        config( ['cms.translate.fallback' => 'hide'] );
        parent::tearDown();
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'LaravelJsonApi\Laravel\ServiceProvider'
        ] );
    }


    public function testLangFilter()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );

        $response = $this->jsonApi()->expects( 'pages' )->filter( ['lang' => 'de'] )->get( 'cms/pages' );

        $response->assertFetchedMany( [['type' => 'pages', 'id' => $blog->id]] );
        $response->assertJsonPath( 'data.0.attributes.lang', 'de' );
        $response->assertJsonPath( 'data.0.attributes.path', 'blog-de' );
    }


    public function testLangFilterUnpublishedVariant()
    {
        Resource::addVariant( $this->blog()->id, 'fr', $this->user );

        $response = $this->jsonApi()->expects( 'pages' )->filter( ['lang' => 'fr'] )->get( 'cms/pages' );

        $response->assertFetchedNone();
    }


    public function testVariantsList()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );
        Resource::addVariant( $blog->id, 'fr', $this->user );

        $response = $this->jsonApi()->expects( 'pages' )->get( "cms/pages/{$blog->id}" );

        $response->assertFetchedOne( ['type' => 'pages', 'id' => $blog->id] );
        $response->assertJsonPath( 'data.attributes.lang', 'en' );

        $variants = $response->json( 'data.attributes.variants' );

        $this->assertSame( ['de', 'en'], array_column( $variants, 'lang' ) );
        $this->assertSame( ['blog-de', 'blog'], array_column( $variants, 'path' ) );
    }


    public function testPathFilterFindsVariant()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );

        $response = $this->jsonApi()->expects( 'pages' )->filter( ['path' => 'blog-de'] )->get( 'cms/pages' );

        $response->assertFetchedMany( [['type' => 'pages', 'id' => $blog->id]] );
        $response->assertJsonPath( 'data.0.attributes.lang', 'de' );
    }


    public function testLangFilterLocalizesRelations()
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $blog = $this->blog();
        $this->variant( $root, 'de', 'start' );

        $included = fn() => collect( $this->jsonApi()->expects( 'pages' )->filter( ['lang' => 'de'] )
            ->includePaths( 'children' )->get( "cms/pages/{$root->id}" )->json( 'included' ) ?? [] )
            ->keyBy( 'id' );

        // no German variant of the blog page and fallback is "hide"
        $this->assertFalse( $included()->has( $blog->id ) );

        config( ['cms.translate.fallback' => 'source'] );
        $this->assertSame( 'blog', $included()->get( $blog->id )['attributes']['path'] ?? null );

        config( ['cms.translate.fallback' => 'hide'] );
        $this->variant( $blog, 'de', 'blog-de' );
        $this->assertSame( 'blog-de', $included()->get( $blog->id )['attributes']['path'] ?? null );
    }


    protected function blog() : Page
    {
        return Page::where( 'tag', 'blog' )->firstOrFail();
    }


    protected function variant( Page $page, string $lang, string $path ) : Page
    {
        Resource::addVariant( $page->id, $lang, $this->user );
        Resource::savePage( $page->id, ['path' => $path, 'status' => 1], $this->user, lang: $lang );
        Publication::publish( Page::class, [$page->id], $this->user, lang: $lang );

        return Page::language( $lang )->findOrFail( $page->id );
    }
}
