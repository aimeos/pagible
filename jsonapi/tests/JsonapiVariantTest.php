<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Variants;
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
        Variants::add( $this->blog()->id, 'fr', $this->user );

        $response = $this->jsonApi()->expects( 'pages' )->filter( ['lang' => 'fr'] )->get( 'cms/pages' );

        $response->assertFetchedNone();
    }


    public function testLangFilterInvalid()
    {
        foreach( ["de\0", '<x>', 'EN'] as $lang )
        {
            $response = $this->jsonApi()->expects( 'pages' )->filter( ['lang' => $lang] )->get( 'cms/pages' );
            $response->assertFetchedNone();
        }
    }


    public function testVariantsList()
    {
        $blog = $this->blog();
        $this->variant( $blog, 'de', 'blog-de' );
        Variants::add( $blog->id, 'fr', $this->user );

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
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
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


    protected function blog() : PageView
    {
        return PageView::where( 'tag', 'blog' )->firstOrFail();
    }


    protected function variant( PageView $page, string $lang, string $path ) : PageView
    {
        Variants::add( $page->id, $lang, $this->user );
        Variants::save( $page->id, $lang, ['path' => $path, 'status' => 1], $this->user );
        Variants::publishMany( [$page->id], $lang, $this->user );

        return PageView::language( $lang )->findOrFail( $page->id );
    }
}
