<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class GraphqlVariantTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->bootRefreshesSchemaCache();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    public function testPageLanguage()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['title' => 'Deutsch'], $this->user, lang: 'de' );

        $response = $this->actingAs( $this->user )->graphQL( '{
            page(id: "' . $page->id . '", lang: "de") {
                id
                lang
                source
                stale
                title
            }
        }' )->assertGraphQLErrorFree();

        $response->assertJson( ['data' => ['page' => [
            'id' => $page->id,
            'lang' => 'de',
            'source' => 'en',
            'stale' => true,
        ]]] );

        $this->assertNull( $this->actingAs( $this->user )->graphQL( '{
            page(id: "' . $page->id . '", lang: "fr") { id }
        }' )->json( 'data.page' ) );
    }


    public function testPageVariants()
    {
        $page = $this->page();
        $other = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Publication::publish( Page::class, [$page->id], $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {id: ["' . $page->id . '", "' . $other->id . '"]}) {
                data {
                    id
                    variants { id lang source state published }
                }
            }
        }' )->assertGraphQLErrorFree();

        $items = collect( $response->json( 'data.pages.data' ) )->keyBy( 'id' );

        $this->assertEquals( [
            ['id' => $page->variant_id, 'lang' => 'en', 'source' => true, 'state' => 'current', 'published' => true],
            ['id' => PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'id' ), 'lang' => 'de', 'source' => false, 'state' => 'stale', 'published' => false],
        ], $items[$page->id]['variants'] );

        $this->assertEquals( ['current', 'missing'], array_column( $items[$other->id]['variants'], 'state' ) );
        $this->assertNull( $items[$other->id]['variants'][1]['id'] );

        Resource::dropVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '{
            page(id: "' . $page->id . '") { variants { lang state } }
        }' )->assertGraphQLErrorFree();

        $this->assertEquals( [['lang' => 'en', 'state' => 'current'], ['lang' => 'de', 'state' => 'trashed']], $response->json( 'data.page.variants' ) );
    }


    public function testPagesFilterLanguage()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {lang: "de"}) {
                data { id lang source }
            }
        }' )->assertGraphQLErrorFree();

        $this->assertContains( ['id' => $page->id, 'lang' => 'de', 'source' => 'en'], $response->json( 'data.pages.data' ) );
    }


    public function testAddPageDefaultLanguage()
    {
        config( ['app.locale' => 'de'] );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addPage(input: {name: "Default", path: "default-lang"}) { id lang source }
            }
        ' )->assertGraphQLErrorFree();

        config( ['app.locale' => 'en'] );

        $this->assertEquals( 'de', $response->json( 'data.addPage.lang' ) );
        $this->assertEquals( 'de', $response->json( 'data.addPage.source' ) );
    }


    public function testAddVariant()
    {
        $page = $this->page();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addVariant(id: "' . $page->id . '", lang: "de") { id lang source stale latest { lang } }
            }
        ' )->assertGraphQLErrorFree();

        $response->assertJson( ['data' => ['addVariant' => [
            'id' => $page->id,
            'lang' => 'de',
            'source' => 'en',
            'stale' => true,
            'latest' => ['lang' => 'de'],
        ]]] );
    }


    public function testAddVariantTrashed()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::dropVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                addVariant(id: "' . $page->id . '", lang: "de") { id }
            }
        ' );

        $this->assertNotEmpty( $response->json( 'errors' ) );
        $this->assertEquals( 1, PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->count() );
    }


    public function testAddVariantPermission()
    {
        $page = $this->page();
        $user = new \App\Models\User( ['cmsperms' => ['page:view', 'page:save']] );

        $this->actingAs( $user )->graphQL( '
            mutation {
                addVariant(id: "' . $page->id . '", lang: "de") { id }
            }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testSavePageLanguages()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $en = Page::findOrFail( $page->id )->latest_id;
        $de = Page::language( 'de' )->findOrFail( $page->id )->latest_id;

        $this->actingAs( $this->user )->graphQL( '
            mutation {
                savePage(id: "' . $page->id . '", input: {title: "English"}, latestId: "' . $en . '") { id }
            }
        ' )->assertGraphQLErrorFree();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                savePage(id: "' . $page->id . '", input: {title: "Deutsch"}, latestId: "' . $de . '", lang: "de") {
                    lang
                    latest { lang data }
                }
            }
        ' )->assertGraphQLErrorFree();

        $this->assertEquals( 'de', $response->json( 'data.savePage.lang' ) );
        $this->assertEquals( 'de', $response->json( 'data.savePage.latest.lang' ) );
        $this->assertEquals( 'English', Page::findOrFail( $page->id )->latest->data->title );
        $this->assertEquals( 'Deutsch', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testSavePageSource()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $versions = Page::findOrFail( $page->id )->versions()->count();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                savePage(id: "' . $page->id . '", input: {source: "de"}, lang: "de") { lang source stale }
            }
        ' )->assertGraphQLErrorFree();

        $response->assertJson( ['data' => ['savePage' => ['lang' => 'de', 'source' => 'de', 'stale' => false]]] );
        $this->assertEquals( 'de', Page::findOrFail( $page->id )->lang );
        $this->assertEquals( [], PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->hashes );
        $this->assertEquals( $versions, Page::language( 'en' )->findOrFail( $page->id )->versions()->count() );
    }


    public function testSavePageSourceWithoutVariant()
    {
        $page = $this->page();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                savePage(id: "' . $page->id . '", input: {source: "de"}) { source }
            }
        ' );

        $this->assertNotEmpty( $response->json( 'errors' ) );
        $this->assertEquals( 'en', Page::findOrFail( $page->id )->source );
    }


    public function testDropKeepPageLanguage()
    {
        $page = $this->page();
        $other = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                dropPage(id: ["' . $page->id . '", "' . $other->id . '"], lang: "de") { id lang variant_deleted_at }
            }
        ' )->assertGraphQLErrorFree();

        // the page without a "de" variant is skipped
        $this->assertCount( 1, $response->json( 'data.dropPage' ) );
        $this->assertEquals( 'de', $response->json( 'data.dropPage.0.lang' ) );
        $this->assertNotNull( $response->json( 'data.dropPage.0.variant_deleted_at' ) );
        $this->assertNotNull( Page::findOrFail( $page->id ) );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );

        // trashing and restoring the page keeps the variant trashed
        Resource::drop( Page::class, [$page->id], $this->user );
        Resource::restore( Page::class, [$page->id], $this->user );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                keepPage(id: ["' . $page->id . '"], lang: "de") { id lang variant_deleted_at }
            }
        ' )->assertGraphQLErrorFree();

        $response->assertJson( ['data' => ['keepPage' => [['id' => $page->id, 'lang' => 'de', 'variant_deleted_at' => null]]]] );
        $this->assertNotNull( Page::language( 'de' )->find( $page->id ) );
    }


    public function testDropPageSourceLanguage()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                dropPage(id: ["' . $page->id . '"], lang: "en") { id }
            }
        ' )->assertGraphQLErrorFree();

        $this->assertEquals( [], $response->json( 'data.dropPage' ) );
        $this->assertNotNull( Page::find( $page->id ) );
    }


    public function testPurgePageLanguage()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                purgePage(id: ["' . $page->id . '"], lang: "de") { id }
            }
        ' )->assertGraphQLErrorFree();

        $this->assertCount( 1, $response->json( 'data.purgePage' ) );
        $this->assertEquals( 0, PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->count() );
        $this->assertNotNull( Page::find( $page->id ) );
    }


    public function testPubPageLanguage()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['title' => 'Deutsch'], $this->user, lang: 'de' );

        $this->actingAs( $this->user )->graphQL( '
            mutation {
                pubPage(id: ["' . $page->id . '"], lang: "de") { id }
            }
        ' )->assertGraphQLErrorFree();

        $this->assertEquals( 'Deutsch', Page::language( 'de' )->findOrFail( $page->id )->title );
        $this->assertFalse( (bool) Page::findOrFail( $page->id )->latest->published );
    }


    public function testCopyPage()
    {
        $page = $this->page();
        $child = Resource::addPage( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid(),
            'content' => [['id' => 'el2', 'type' => 'text', 'data' => ['text' => 'Child']]],
        ], $this->user, parent: $page->id );

        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $child->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                copyPage(id: "' . $page->id . '", parent: "' . $this->root()->id . '") { id source path }
            }
        ' )->assertGraphQLErrorFree();

        $id = $response->json( 'data.copyPage.id' );

        $this->assertNotEquals( $page->id, $id );
        $this->assertEquals( 'en', $response->json( 'data.copyPage.source' ) );
        $this->assertNotEquals( $page->path, $response->json( 'data.copyPage.path' ) );
        $this->assertEquals(
            PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'hashes' ),
            PageVariant::where( 'page_id', $id )->where( 'lang', 'de' )->value( 'hashes' )
        );
        $this->assertEquals( 'el1', Page::language( 'de' )->findOrFail( $id )->latest->aux->content[0]->id );

        $children = Page::where( 'parent_id', $id )->get();
        $this->assertCount( 1, $children );
        $this->assertEquals( 'el2', Page::language( 'de' )->findOrFail( $children[0]->id )->latest->aux->content[0]->id );
    }


    protected function page() : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'var-' . Utils::uid(),
            'content' => [['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']]],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
