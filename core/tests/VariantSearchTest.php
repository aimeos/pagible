<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
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
        $page = Page::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'fr']] );

        $variant = Resource::addVariant( $page->id, 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );

        Scout::sync( Page::class, [(string) $page->id] );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs'] ?? null );

        Resource::dropVariant( $page->id, 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs'] ?? null );
        $this->assertEqualsCanonicalizing( [$source, 'fr'], $doc['langs_trashed'] ?? null );
        $this->assertSame( [], $this->engine->documents[$variant->variant_id]['langs'] ?? null );
        $this->assertSame( ['de'], $this->engine->documents[$variant->variant_id]['langs_trashed'] ?? null );

        Resource::purgeVariant( $page->id, 'de' );

        $doc = $this->engine->documents[$page->variant_id] ?? [];
        $this->assertEqualsCanonicalizing( [$source, 'de', 'fr'], $doc['langs_trashed'] ?? null );
    }


    public function testSyncIndexesAllVariantsOfThePages(): void
    {
        $page = Page::where( 'path', 'blog' )->firstOrFail();
        $variant = Resource::addVariant( $page->id, 'de' );
        $this->engine->documents = [];

        Scout::sync( Page::class, [(string) $page->id] );

        $this->assertEqualsCanonicalizing( [$page->variant_id, $variant->variant_id], array_keys( $this->engine->documents ) );
    }


    public function testSourcesAreReindexedOnceAfterCommit(): void
    {
        $page = Page::where( 'path', 'blog' )->firstOrFail();
        $source = (string) $page->lang;
        config( ['cms.locales' => [$source, 'de', 'fr']] );
        $this->engine->updates = [];

        ( new Page() )->getConnection()->transaction( function() use ( $page ) {
            Scout::sources( [(string) $page->id] );
            Scout::sources( [(string) $page->id] );

            $this->assertSame( [], $this->engine->updates );
        } );

        $this->assertSame( 1, count( array_keys( $this->engine->updates, $page->variant_id, true ) ) );
    }


    public function testPreferFiltersTheEngineByLanguage(): void
    {
        $search = Page::search( 'blog' );
        Scout::prefer( $search, 'de' );
        $this->assertSame( 'de', $this->where( $search, 'langs' ) );

        $search = Page::search( 'blog' );
        Scout::prefer( $search, 'de', 'with' );
        $this->assertSame( 'de', $this->where( $search, 'langs_trashed' ) );

        config( ['scout.driver' => 'collection'] );

        $search = Page::search( 'blog' );
        Scout::prefer( $search, 'de' );
        $this->assertNull( $this->where( $search, 'langs' ) );
    }


    /**
     * @param \Laravel\Scout\Builder<Page> $search
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


    /** @param \Illuminate\Database\Eloquent\Collection<int, Page> $models */
    public function update( $models ) : void
    {
        foreach( $models as $model )
        {
            if( $model instanceof Page && ( $doc = $model->toSearchableArray() ) !== [] ) {
                $this->documents[(string) $model->getScoutKey()] = $doc;
                $this->updates[] = (string) $model->getScoutKey();
            }
        }
    }
}
