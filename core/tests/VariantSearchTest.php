<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\SearchQuery;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Scout;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;


class VariantSearchTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;
    private VariantSearchEngineSpy $engine;
    private mixed $locales;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'scout.queue', false );
        $app['config']->set( 'scout.soft_delete', true );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new VariantSearchEngineSpy();
        $engine = $this->engine;
        $manager = app( EngineManager::class );
        $manager->extend( 'variant-test', fn() => $engine );
        $manager->forgetDrivers();

        $this->locales = config( 'cms.locales' );
        config( ['scout.driver' => 'variant-test'] );
    }


    protected function tearDown(): void
    {
        config( ['cms.locales' => $this->locales, 'scout.driver' => 'collection', 'scout.soft_delete' => false] );
        app( EngineManager::class )->forgetDrivers();

        parent::tearDown();
    }


    public function testSourceIsListedForLanguagesWithoutVariant(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'fr']] );

        $variant = Permission::system( fn() => Variants::add( $page->id, 'de' ) );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );

        Scout::sync( PageView::class, [(string) $page->id] );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs'] ?? null );

        Permission::system( fn() => Variants::drop( $page->id, 'de' ) );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );
        $this->assertSame( [], $this->engine->documents[$variant->variant_id]['langs'] ?? null );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs_trashed'] ?? null );

        Permission::system( fn() => Variants::purgeMany( [$page->id], 'de' ) );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs_trashed'] ?? null );
    }


    public function testFallbackVariantIsListedForRegionalLanguages(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'de-CH']] );

        $variant = Permission::system( fn() => Variants::add( $page->id, 'de' ) );

        // the variants of all languages are reindexed because "de-CH" falls back to "de"
        $this->assertSame( ['de', 'de-CH'], $this->engine->documents[$variant->variant_id]['langs'] ?? null );
        $this->assertSame( [$source], $this->engine->documents[$page->variant_id]['langs'] ?? null );

        Permission::system( fn() => Variants::drop( $page->id, 'de' ) );

        $this->assertSame( [], $this->engine->documents[$variant->variant_id]['langs'] ?? null );
        $this->assertSame( ['de', 'de-CH'], $this->engine->documents[$variant->variant_id]['langs_trashed'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'de', 'de-CH'], $this->engine->documents[$page->variant_id]['langs'] ?? null );
    }


    public function testIndexedLanguagesMatchTheFallback(): void
    {
        $pages = PageView::language( null )->orderBy( 'id' )->limit( 4 )->get();
        $ids = $pages->pluck( 'id' )->map( fn( $id ) => (string) $id )->all();
        $locales = array_values( array_unique( [...$pages->pluck( 'source' )->all(), 'de', 'de-CH', 'fr', 'lb'] ) );
        // "lb" falls back to two languages, so the first existing one must be used
        config( ['cms.locales' => $locales, 'cms.translate.chains' => ['lb' => ['de', 'fr']]] );
        mt_srand( 4321 );

        foreach( $ids as $id )
        {
            Permission::system( fn() => Variants::add( $id, 'de' ) );
            Permission::system( fn() => Variants::add( $id, 'de-CH' ) );
            Permission::system( fn() => Variants::add( $id, 'fr' ) );
        }

        $variants = PageVariant::withTrashed()->whereIn( 'page_id', $ids )->whereNotIn( 'id', $pages->pluck( 'variant_id' ) )->pluck( 'lang', 'id' );

        // the languages listed in the index must select the same variants as the fallback query
        for( $round = 0; $round < 5; $round++ )
        {
            foreach( $variants as $vid => $lang )
            {
                // renamed, trashed or unchanged, source variants can't be renamed or trashed
                $state = mt_rand( 0, 2 );

                PageVariant::withTrashed()->where( 'id', $vid )->update( [
                    'lang' => $state === 0 ? 'x' . strtolower( substr( $lang, -2 ) ) : $lang,
                    'deleted_at' => $state === 1 ? now() : null,
                ] );
            }

            Scout::sync( PageView::class, $ids );
            $this->assertListed( $ids, $locales, 'Round ' . $round );
        }

        mt_srand();
    }


    public function testIndexJobQueuedByOlderVersion(): void
    {
        // jobs serialized before the "keys" property existed don't contain it
        $job = ( new \ReflectionClass( \Aimeos\Cms\Jobs\IndexModels::class ) )->newInstanceWithoutConstructor();
        $job->__unserialize( ['model' => PageView::class, 'ids' => [], 'tenant' => 'test'] );

        $this->assertFalse( $job->keys );
    }


    public function testSyncIndexesAllVariantsOfThePages(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $variant = Permission::system( fn() => Variants::add( $page->id, 'de' ) );
        $this->engine->documents = [];

        Scout::sync( PageView::class, [(string) $page->id] );

        $this->assertEqualsCanonicalizing( [$page->variant_id, $variant->variant_id], array_keys( $this->engine->documents ) );
    }


    public function testBulkAndPublishIndexOnlyTheChangedVariants(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $variant = Permission::system( fn() => Variants::add( $page->id, 'de' ) );
        $this->engine->updates = [];

        Permission::system( fn() => Variants::bulk( [$page->id], 'de', ['title' => 'Blog DE'] ) );
        $this->assertSame( [$variant->variant_id], $this->engine->updates );

        $this->engine->updates = [];
        config( ['scout.queue' => true] );
        \Illuminate\Support\Facades\Queue::fake();

        try {
            Permission::system( fn() => Variants::publishMany( [$page->id], 'de' ) );
        } finally {
            config( ['scout.queue' => false] );
        }

        \Illuminate\Support\Facades\Queue::assertPushed( \Aimeos\Cms\Jobs\IndexModels::class,
            fn( $job ) => $job->ids === [$variant->variant_id] && $job->keys );
    }


    public function testBulkVariantRestoreReindexesTheVariantsOfThePages(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $de = Permission::system( fn() => Variants::add( $page->id, 'de' ) );
        $fr = Permission::system( fn() => Variants::add( $page->id, 'fr' ) );
        Permission::system( fn() => Variants::drop( $page->id, 'de' ) );

        $this->engine->updates = [];
        Permission::system( fn() => Variants::restoreMany( [$page->id], 'de', fields: ['name'] ) );

        // the source variant isn't listed for "de" any more, the languages of "fr" don't change
        $this->assertEqualsCanonicalizing( [$page->variant_id, $de->variant_id], array_values( array_unique( $this->engine->updates ) ) );
        $this->assertNotContains( $fr->variant_id, $this->engine->updates );
    }


    public function testCopiesAreIndexedOnceWithTheirAccess(): void
    {
        $root = PageView::where( 'path', 'blog' )->firstOrFail();
        $page = Permission::system( fn() => Pages::add( ['lang' => 'en', 'name' => 'Copy', 'path' => 'copy-page'], parent: $root->id ) );

        \Aimeos\Cms\Models\PageAccess::query()->toBase()->insert( [
            'page_id' => $page->id, 'tenant_id' => 'test', 'value' => '', 'editor' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ] );

        // single page, no bulk inserts
        $this->engine->updates = [];
        $copy = Permission::system( fn() => Pages::copy( $page->id, parent: $root->id ) );

        $this->assertSame( [$copy->variant_id], $this->engine->updates );
        $this->assertTrue( $this->engine->documents[$copy->variant_id]['restricted'] ?? null );

        // subtree with bulk inserted variants
        Permission::system( fn() => Variants::add( $copy->id, 'de' ) );
        Permission::system( fn() => Pages::add( ['lang' => 'en', 'name' => 'Child', 'path' => 'copy-child'], parent: $copy->id ) );

        $this->engine->updates = [];
        $tree = Permission::system( fn() => Pages::copy( $copy->id, parent: $root->id ) );
        $keys = \Aimeos\Cms\Models\PageVariant::whereIn( 'page_id', PageView::where( 'parent_id', $tree->id )->pluck( 'id' )->push( $tree->id ) )
            ->pluck( 'id' )->map( strval( ... ) )->all();

        $this->assertCount( 3, $keys );
        $this->assertEqualsCanonicalizing( $keys, $this->engine->updates );
        $this->assertTrue( $this->engine->documents[$tree->variant_id]['restricted'] ?? null );
    }


    public function testReindexedVariantsMatchTheFallback(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $id = (string) $page->id;
        $locales = [(string) $page->lang, 'de', 'de-CH', 'fr', 'lb'];
        config( ['cms.locales' => $locales, 'cms.translate.chains' => ['lb' => ['de', 'fr']]] );
        Scout::sync( PageView::class, [$id] );

        $steps = [
            'add fr' => fn() => Permission::system( fn() => Variants::add( $id, 'fr' ) ),
            'add de' => fn() => Permission::system( fn() => Variants::add( $id, 'de' ) ),
            'add de-CH' => fn() => Permission::system( fn() => Variants::add( $id, 'de-CH' ) ),
            'drop de' => fn() => Permission::system( fn() => Variants::drop( $id, 'de' ) ),
            'source fr' => fn() => Permission::system( fn() => Variants::setSource( $id, 'fr' ) ),
            'restore de' => fn() => Permission::system( fn() => Variants::restoreMany( [$id], 'de' ) ),
            'drop de-CH' => fn() => Permission::system( fn() => Variants::drop( $id, 'de-CH' ) ),
            'purge de-CH' => fn() => Permission::system( fn() => Variants::purgeMany( [$id], 'de-CH' ) ),
        ];

        // only the affected variants are reindexed, so the others must still be listed correctly
        foreach( $steps as $name => $step )
        {
            $step();
            $this->assertListed( [$id], $locales, $name );
        }
    }


    public function testOnlyVariantsOfAffectedLanguagesAreReindexed(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        config( ['cms.locales' => [(string) $page->lang, 'de', 'de-CH', 'fr']] );

        $de = Permission::system( fn() => Variants::add( $page->id, 'de' ) );
        $fr = Permission::system( fn() => Variants::add( $page->id, 'fr' ) );
        $this->engine->updates = [];

        // "de-CH" falls back to "de", so the variants of "fr" keep their languages
        $variant = Permission::system( fn() => Variants::add( $page->id, 'de-CH' ) );

        $this->assertEqualsCanonicalizing( [$page->variant_id, $de->variant_id, $variant->variant_id], array_unique( $this->engine->updates ) );
        $this->assertSame( ['de'], $this->engine->documents[$de->variant_id]['langs'] ?? null );
        $this->assertNotContains( $fr->variant_id, $this->engine->updates );
    }


    public function testRenamedVariantReindexesTheOtherVariants(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $id = (string) $page->id;
        $locales = [(string) $page->lang, 'de', 'de-CH', 'fr'];
        config( ['cms.locales' => $locales] );

        $de = Permission::system( fn() => Variants::add( $id, 'de' ) );
        PageVariant::findOrFail( $de->variant_id )->forceFill( ['lang' => 'fr'] )->save();

        // "de" and "de-CH" fall back to the source variant again
        $this->assertListed( [$id], $locales, 'rename de' );
    }


    public function testTrashedVariantsAreNotReindexedWithoutSoftDeletes(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        config( ['cms.locales' => [(string) $page->lang, 'de', 'de-CH', 'fr'], 'scout.soft_delete' => false] );

        Permission::system( fn() => Variants::add( $page->id, 'de' ) );
        $variant = Permission::system( fn() => Variants::add( $page->id, 'de-CH' ) );
        $this->engine->updates = [];

        // the dropped variant is removed from the index and must not be added again
        Permission::system( fn() => Variants::drop( $page->id, 'de-CH' ) );
        Permission::system( fn() => Variants::add( $page->id, 'fr' ) );

        $this->assertNotContains( $variant->variant_id, $this->engine->updates );
    }


    public function testVariantsAreReindexedOnceAfterCommit(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'fr']] );
        $this->engine->updates = [];

        ( new PageView() )->getConnection()->transaction( function() use ( $page ) {
            Scout::variants( [(string) $page->id], ['de'] );
            Scout::variants( [(string) $page->id], ['fr'] );

            $this->assertSame( [], $this->engine->updates );
        } );

        $this->assertSame( 1, count( array_keys( $this->engine->updates, $page->variant_id, true ) ) );
    }


    public function testPreferFiltersTheEngineByLanguage(): void
    {
        $search = PageView::search( 'blog' );
        SearchQuery::prefer( $search, 'de' );
        $this->assertSame( 'de', $this->where( $search, 'langs' ) );

        $search = PageView::search( 'blog' );
        SearchQuery::prefer( $search, 'de', 'with' );
        $this->assertSame( 'de', $this->where( $search, 'langs_trashed' ) );

        config( ['scout.driver' => 'collection'] );

        $search = PageView::search( 'blog' );
        SearchQuery::prefer( $search, 'de' );
        $this->assertNull( $this->where( $search, 'langs' ) );
    }


    public function testPreferInvalidLanguage(): void
    {
        $this->expectException( \InvalidArgumentException::class );
        SearchQuery::prefer( PageView::search( 'blog' ), 'de" OR langs = "en' );
    }


    /**
     * @param \Laravel\Scout\Builder<PageView> $search
     */
    /**
     * Asserts that the indexed variants listed for each language are the ones of the language fallback.
     *
     * @param array<string> $ids Page IDs
     * @param array<string> $locales Language codes
     */
    private function assertListed( array $ids, array $locales, string $msg ) : void
    {
        $vids = array_flip( PageVariant::withTrashed()->whereIn( 'page_id', $ids )->pluck( 'id' )->all() );
        $docs = array_intersect_key( $this->engine->documents, $vids );

        foreach( $locales as $locale )
        {
            foreach( ['langs' => false, 'langs_trashed' => true] as $field => $trashed )
            {
                $expected = PageView::fallback( $locale, $trashed )->whereIn( 'id', $ids )->pluck( 'variant_id' )->all();
                $actual = array_keys( array_filter( $docs, fn( $doc ) => in_array( $locale, $doc[$field] ?? [], true ) ) );

                $this->assertEqualsCanonicalizing( $expected, $actual, sprintf( '%1$s, %2$s, %3$s', $msg, $locale, $field ) );
            }
        }
    }


    private function where( \Laravel\Scout\Builder $search, string $field ) : mixed
    {
        foreach( $search->wheres as $key => $where )
        {
            if( ( $where['field'] ?? $key ) === $field ) {
                return is_array( $where ) ? ( $where['value'] ?? null ) : $where;
            }
        }

        return null;
    }
}


class VariantSearchEngineSpy extends NullEngine
{
    /** @var array<string, array<string, mixed>> */
    public array $documents = [];

    /** @var list<string> */
    public array $updates = [];


    /** @param \Illuminate\Database\Eloquent\Collection<int, PageView> $models */
    public function update( $models ) : void
    {
        foreach( $models as $model )
        {
            if( $model instanceof PageView && ( $doc = $model->toSearchableArray() ) !== [] ) {
                $this->documents[(string) $model->getScoutKey()] = $doc;
                $this->updates[] = (string) $model->getScoutKey();
            }
        }
    }
}
