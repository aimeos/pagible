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


    public function testPagesLanguageFallback()
    {
        $page = $this->page();
        $other = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $query = fn( string $trashed ) => $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {id: ["' . $page->id . '", "' . $other->id . '"]}, lang: "de", trashed: ' . $trashed . ') {
                data { id lang variant_deleted_at }
            }
        }' )->assertGraphQLErrorFree()->json( 'data.pages.data' );

        $result = collect( $query( 'WITHOUT' ) )->keyBy( 'id' );
        $this->assertCount( 2, $result );
        $this->assertEquals( 'de', $result[$page->id]['lang'] );
        $this->assertEquals( 'en', $result[$other->id]['lang'] );
        $this->assertEquals( [], $query( 'ONLY' ) );

        // a trashed variant falls back to the source variant unless trashed ones are requested
        Resource::dropVariant( $page->id, 'de', $this->user );

        $result = collect( $query( 'WITHOUT' ) )->keyBy( 'id' );
        $this->assertEquals( 'en', $result[$page->id]['lang'] );

        $result = collect( $query( 'WITH' ) )->keyBy( 'id' );
        $this->assertCount( 2, $result );
        $this->assertEquals( 'de', $result[$page->id]['lang'] );
        $this->assertNotNull( $result[$page->id]['variant_deleted_at'] );

        $result = $query( 'ONLY' );
        $this->assertCount( 1, $result );
        $this->assertEquals( [$page->id, 'de'], [$result[0]['id'], $result[0]['lang']] );
    }


    public function testBulkPageLanguage()
    {
        $page = $this->page();
        $other = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation {
                bulkPage(id: ["' . $page->id . '", "' . $other->id . '"], input: {status: 1}, lang: "de") { ids failed data }
            }
        ' )->assertGraphQLErrorFree();

        // the page without a "de" variant is skipped
        $response->assertJson( ['data' => ['bulkPage' => ['ids' => [$page->id], 'failed' => 1]]] );
        $this->assertEquals( 'de', json_decode( $response->json( 'data.bulkPage.data' ) ?? '{}' )->lang ?? null );
        $this->assertEquals( 1, Page::language( 'de' )->findOrFail( $page->id )->latest->data->status ?? null );
        $this->assertNotEquals( 1, Page::findOrFail( $page->id )->latest->data->status ?? null );
        $this->assertNotEquals( 1, Page::findOrFail( $other->id )->latest->data->status ?? null );
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


    public function testPageLanguageTooLong()
    {
        $page = $this->page();

        foreach( [str_repeat( 'x', 11 ), '<x>', 'EN'] as $lang )
        {
            foreach( ['dropPage', 'keepPage', 'purgePage', 'pubPage'] as $name )
            {
                $this->actingAs( $this->user )->graphQL( '
                    mutation {
                        ' . $name . '(id: ["' . $page->id . '"], lang: "' . $lang . '") { id }
                    }
                ' )->assertGraphQLErrorMessage( 'Validation failed for the field [' . $name . '].' );
            }
        }

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


    public function testPagesFilterTranslation()
    {
        $counts = fn() => $this->actingAs( $this->user )->graphQL( '{
            pageTranslationStates(langs: ["de"]) { stale missing ai }
        }' )->assertGraphQLErrorFree()->json( 'data.pageTranslationStates.0' );

        $before = $counts();

        $ai = $this->page();
        $missing = $this->page();
        $stale = $this->page();

        Resource::translatePage( $ai->id, 'de', $this->user, $this->translator() );
        Resource::addVariant( $stale->id, 'de', $this->user );

        $query = fn( string $state ) => array_column( $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {id: ["' . $ai->id . '", "' . $missing->id . '", "' . $stale->id . '"], translation: "' . $state . '"}, lang: "de") {
                data { id lang }
            }
        }' )->assertGraphQLErrorFree()->json( 'data.pages.data' ), 'lang', 'id' );

        $this->assertEquals( [$missing->id => 'en'], $query( 'missing' ) );
        $this->assertEquals( [$stale->id => 'de'], $query( 'stale' ) );
        $this->assertEquals( [$ai->id => 'de'], $query( 'ai' ) );

        $this->assertEquals( [
            'stale' => $before['stale'] + 1,
            'missing' => $before['missing'] + 1,
            'ai' => $before['ai'] + 1,
        ], $counts() );

        $this->actingAs( $this->user )->graphQL( '{
            pages(filter: {translation: "invalid"}, lang: "de") { data { id } }
        }' )->assertGraphQLValidationKeys( ['filter.translation'] );
    }


    public function testPageTranslationStates()
    {
        $locales = config( 'cms.locales' );
        config( ['cms.locales' => ['en', 'de', 'fr']] );

        try
        {
            $states = fn() => array_column( $this->actingAs( $this->user )->graphQL( '{
                pageTranslationStates { lang stale missing ai }
            }' )->assertGraphQLErrorFree()->json( 'data.pageTranslationStates' ), null, 'lang' );

            $before = $states();
            $this->assertEquals( ['en', 'de', 'fr'], array_keys( $before ) );

            $page = $this->page();
            Resource::addVariant( $page->id, 'de', $this->user );

            $after = $states();

            $this->assertEquals( $before['de']['stale'] + 1, $after['de']['stale'] );
            $this->assertEquals( $before['de']['missing'], $after['de']['missing'] );
            $this->assertEquals( $before['fr']['missing'] + 1, $after['fr']['missing'] );
            $this->assertEquals( $before['en'], $after['en'] );

            $this->assertEquals( ['fr', 'de'], array_column( $this->actingAs( $this->user )->graphQL( '{
                pageTranslationStates(langs: ["fr", "de", "fr"]) { lang }
            }' )->assertGraphQLErrorFree()->json( 'data.pageTranslationStates' ), 'lang' ) );
        }
        finally
        {
            config( ['cms.locales' => $locales] );
        }
    }


    public function testPageTranslationStatesCached()
    {
        $states = fn( string $args ) => $this->actingAs( $this->user )->graphQL( '{
            pageTranslationStates(langs: ["de"]' . $args . ') { stale }
        }' )->assertGraphQLErrorFree()->json( 'data.pageTranslationStates.0' );

        $before = $states( '' );
        $page = $this->page();

        Resource::addVariant( $page->id, 'de', $this->user );

        // cached counts are used until fresh ones are requested
        $this->assertEquals( ['stale' => $before['stale']], $states( ', cached: true' ) );
        $this->assertEquals( ['stale' => $before['stale'] + 1], $states( '' ) );
        $this->assertEquals( ['stale' => $before['stale'] + 1], $states( ', cached: true' ) );
    }


    public function testPageTranslationStatesFreshLimited()
    {
        $states = fn() => $this->actingAs( $this->user )->graphQL( '{
            pageTranslationStates(langs: ["de"]) { stale }
        }' )->assertGraphQLErrorFree()->json( 'data.pageTranslationStates.0' );

        $before = $states();
        \Illuminate\Support\Facades\RateLimiter::increment( 'cms-translation-states:' . sha1( \Aimeos\Cms\Tenancy::value() . ':' . $this->user->id ), 60, 10 );

        Resource::addVariant( $this->page()->id, 'de', $this->user );

        // fresh counts scan all variants, so they are computed at most ten times a minute
        $this->assertEquals( $before, $states() );

        $this->travel( 61 )->seconds();
        $this->assertEquals( ['stale' => $before['stale'] + 1], $states() );
    }


    public function testSavePageRestore()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::ignoreVariants( [$page->id], 'de', $this->user );

        $this->actingAs( $this->user )->graphQL( '
            mutation { savePage(id: "' . $page->id . '", input: {title: "Alt"}, lang: "de") { stale } }
        ' )->assertJson( ['data' => ['savePage' => ['stale' => false]]] );

        $this->actingAs( $this->user )->graphQL( '
            mutation { savePage(id: "' . $page->id . '", input: {title: "Restored"}, lang: "de", restore: true) { stale } }
        ' )->assertJson( ['data' => ['savePage' => ['stale' => true]]] );

        $this->assertTrue( (bool) Page::language( 'de' )->findOrFail( $page->id )->stale );
    }


    public function testIgnoreChanges()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $this->assertTrue( (bool) Page::language( 'de' )->findOrFail( $page->id )->stale );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { ignoreChanges(id: $id, lang: "de") { id lang stale } }
        ', ['id' => [$page->id]] )->assertJson( ['data' => ['ignoreChanges' => [['id' => $page->id, 'lang' => 'de', 'stale' => false]]]] );

        $this->assertFalse( (bool) Page::language( 'de' )->findOrFail( $page->id )->stale );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { ignoreChanges(id: $id, lang: "en") { id } }
        ', ['id' => [$page->id]] )->assertGraphQLErrorMessage( 'The source language can\'t be marked as up to date' );
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


    /**
     * Returns a translate callback prefixing the texts with the language.
     */
    protected function translator() : \Closure
    {
        return fn( array $texts, string $to ) => array_map( fn( $text ) => '[' . $to . '] ' . $text, $texts );
    }
}
