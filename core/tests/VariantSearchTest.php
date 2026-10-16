<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

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

        $variant = Variants::add( $page->id, 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );

        Scout::sync( PageView::class, [(string) $page->id] );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs'] ?? null );

        Variants::drop( $page->id, 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );
        $this->assertSame( [], $this->engine->documents[$variant->variant_id]['langs'] ?? null );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs_trashed'] ?? null );

        Variants::purgeMany( [$page->id], 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs_trashed'] ?? null );
    }


    public function testIndexJobQueuedByOlderVersion(): void
    {
        // jobs serialized before the "sources" and "keys" properties existed don't contain them
        $job = ( new \ReflectionClass( \Aimeos\Cms\Jobs\IndexModels::class ) )->newInstanceWithoutConstructor();
        $job->__unserialize( ['model' => PageView::class, 'ids' => [], 'tenant' => 'test'] );

        $this->assertFalse( $job->sources );
        $this->assertFalse( $job->keys );
    }


    public function testSyncIndexesAllVariantsOfThePages(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $variant = Variants::add( $page->id, 'de' );
        $this->engine->documents = [];

        Scout::sync( PageView::class, [(string) $page->id] );

        $this->assertEqualsCanonicalizing( [$page->variant_id, $variant->variant_id], array_keys( $this->engine->documents ) );
    }


    public function testBulkAndPublishIndexOnlyTheChangedVariants(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $variant = Variants::add( $page->id, 'de' );
        $this->engine->updates = [];

        Variants::bulk( [$page->id], 'de', ['title' => 'Blog DE'], null );
        $this->assertSame( [$variant->variant_id], $this->engine->updates );

        $this->engine->updates = [];
        config( ['scout.queue' => true] );
        \Illuminate\Support\Facades\Queue::fake();

        try {
            Variants::publishMany( [$page->id], 'de', null );
        } finally {
            config( ['scout.queue' => false] );
        }

        \Illuminate\Support\Facades\Queue::assertPushed( \Aimeos\Cms\Jobs\IndexModels::class,
            fn( $job ) => $job->ids === [$variant->variant_id] && $job->keys );
    }


    public function testBulkVariantRestoreIndexesOnlyTheRestoredVariants(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $de = Variants::add( $page->id, 'de' );
        $fr = Variants::add( $page->id, 'fr' );
        Variants::drop( $page->id, 'de' );

        $this->engine->updates = [];
        Variants::restoreMany( [$page->id], 'de', fields: ['name'] );

        // the source variant is reindexed for its language list, the other variants are unchanged
        $this->assertContains( $de->variant_id, $this->engine->updates );
        $this->assertNotContains( $fr->variant_id, $this->engine->updates );
    }


    public function testCopiesAreIndexedOnceWithTheirAccess(): void
    {
        $root = PageView::where( 'path', 'blog' )->firstOrFail();
        $page = Pages::add( ['lang' => 'en', 'name' => 'Copy', 'path' => 'copy-page'], parent: $root->id );

        \Aimeos\Cms\Models\PageAccess::query()->toBase()->insert( [
            'page_id' => $page->id, 'tenant_id' => 'test', 'value' => '', 'editor' => 'test', 'created_at' => now(), 'updated_at' => now(),
        ] );

        // single page, no bulk inserts
        $this->engine->updates = [];
        $copy = Pages::copy( $page->id, parent: $root->id );

        $this->assertSame( [$copy->variant_id], $this->engine->updates );
        $this->assertTrue( $this->engine->documents[$copy->variant_id]['restricted'] ?? null );

        // subtree with bulk inserted variants
        Variants::add( $copy->id, 'de' );
        Pages::add( ['lang' => 'en', 'name' => 'Child', 'path' => 'copy-child'], parent: $copy->id );

        $this->engine->updates = [];
        $tree = Pages::copy( $copy->id, parent: $root->id );
        $keys = \Aimeos\Cms\Models\PageVariant::whereIn( 'page_id', PageView::where( 'parent_id', $tree->id )->pluck( 'id' )->push( $tree->id ) )
            ->pluck( 'id' )->map( strval( ... ) )->all();

        $this->assertCount( 3, $keys );
        $this->assertEqualsCanonicalizing( $keys, $this->engine->updates );
        $this->assertTrue( $this->engine->documents[$tree->variant_id]['restricted'] ?? null );
    }


    public function testSourcesAreReindexedOnceAfterCommit(): void
    {
        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'fr']] );
        $this->engine->updates = [];

        ( new PageView() )->getConnection()->transaction( function() use ( $page ) {
            Scout::sources( [(string) $page->id] );
            Scout::sources( [(string) $page->id] );

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
