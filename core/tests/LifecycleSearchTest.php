<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Facades\Elements;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;


class LifecycleSearchTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;
    private LifecycleSearchEngineSpy $engine;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'scout.queue', false );
        $app['config']->set( 'scout.soft_delete', true );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new LifecycleSearchEngineSpy();
        $engine = $this->engine;
        $manager = app( EngineManager::class );
        $manager->extend( 'lifecycle-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'lifecycle-test'] );

        $this->user = new \App\Models\User( [
            'name' => 'editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] );

        Event::listen( Dropped::class, fn() => null );
        Event::listen( Restored::class, fn() => null );
    }


    public function testElementSoftDropUsesCompleteLatestVersionForSearch(): void
    {
        $element = Element::query()->with( 'latest' )->firstOrFail();
        $latest = $element->latest;

        $this->assertNotNull( $latest );
        $expected = mb_strtolower( (string) $latest );

        Elements::dropMany( [(string) $element->id], $this->user );

        $document = $this->engine->document( Element::class );

        $this->assertNotNull( $document );
        $this->assertSame( $expected, $document['draft'] );
        $this->assertSame( $latest->editor, $document['editor'] );
        $this->assertSame( $latest->data?->type, $document['type'] );
    }


    public function testFileRestoreUsesCompleteLatestVersionForSearch(): void
    {
        $file = File::query()->with( 'latest' )->firstOrFail();
        $latest = $file->latest;

        $this->assertNotNull( $latest );
        $expected = mb_strtolower( (string) $latest );

        Files::dropMany( [(string) $file->id], $this->user );
        $this->engine->documents = [];
        Files::restoreMany( [(string) $file->id], $this->user );

        $document = $this->engine->document( File::class );

        $this->assertNotNull( $document );
        $this->assertSame( $expected, $document['draft'] );
        $this->assertSame( $latest->editor, $document['editor'] );
        $this->assertSame( $latest->data?->mime, $document['mime'] );
    }


    public function testPublishIndexesPublishedVersionsSynchronously(): void
    {
        $file = File::query()->where( 'mime', 'image/jpeg' )->firstOrFail();
        Files::publishMany( [(string) $file->id], $this->user );

        $this->assertTrue( $this->engine->document( File::class )['published'] ?? false );

        $element = Element::query()->firstOrFail();
        Elements::publishMany( [(string) $element->id], $this->user );

        $this->assertTrue( $this->engine->document( Element::class )['published'] ?? false );

        $page = PageView::query()->where( 'path', 'hidden' )->firstOrFail();
        Pages::save( (string) $page->id, ['title' => 'Publish search test'], $this->user );
        Pages::publishMany( [(string) $page->id], $this->user );

        $this->assertTrue( $this->engine->document( PageView::class )['published'] ?? false );
    }


    public function testScheduleIndexesPageVariant(): void
    {
        $page = PageView::query()->where( 'path', 'hidden' )->firstOrFail();
        Variants::add( (string) $page->id, 'de', $this->user );
        Variants::save( (string) $page->id, 'de', ['path' => 'hidden-de'], $this->user );
        $this->engine->documents = [];

        Variants::publishMany( [(string) $page->id], 'de', $this->user, now()->addDay()->toDateTimeString() );

        $document = $this->engine->document( PageView::class );

        $this->assertSame( 'de', $document['lang'] ?? null );
        $this->assertSame( 1, $document['scheduled'] ?? null );
    }


    public function testRemovalInTransactionWinsOverPendingIndex(): void
    {
        $element = Element::query()->firstOrFail();
        $this->engine->documents = [];

        \Aimeos\Cms\DB::transaction( function() use ( $element ) {
            \Aimeos\Cms\Scout::index( Element::class, [(string) $element->id] );
            \Aimeos\Cms\Scout::unindex( Element::class, [(string) $element->id] );
        } );

        $this->assertArrayNotHasKey( Element::class, $this->engine->documents );
        $this->assertSame( [(string) $element->id], $this->engine->deleted[Element::class] ?? [] );

        \Aimeos\Cms\DB::transaction( function() use ( $element ) {
            \Aimeos\Cms\Scout::unindex( Element::class, [(string) $element->id] );
            \Aimeos\Cms\Scout::index( Element::class, [(string) $element->id] );
        } );

        $this->assertArrayHasKey( Element::class, $this->engine->documents );
        $this->assertSame( [(string) $element->id], $this->engine->deleted[Element::class] ?? [] );
    }


    public function testPageRestoredInTransactionKeepsItsVariants(): void
    {
        config( ['scout.soft_delete' => false] );

        try
        {
            $page = PageView::query()->where( 'path', 'hidden' )->firstOrFail();
            $this->engine->documents = [];

            \Aimeos\Cms\DB::transaction( function() use ( $page ) {
                Pages::dropMany( [(string) $page->id], $this->user );
                Pages::restoreMany( [(string) $page->id], $this->user );
            } );

            $this->assertArrayNotHasKey( PageView::class, $this->engine->deleted );
            $this->assertArrayHasKey( PageView::class, $this->engine->documents );

            \Aimeos\Cms\DB::transaction( function() use ( $page ) {
                Pages::dropMany( [(string) $page->id], $this->user );
                Pages::restoreMany( [(string) $page->id], $this->user );
                Pages::dropMany( [(string) $page->id], $this->user );
            } );

            $this->assertSame( [(string) $page->variant_id], $this->engine->deleted[PageView::class] ?? [] );
        }
        finally
        {
            config( ['scout.soft_delete' => true] );
        }
    }


    public function testRelocateFileDoesNotUpdateSearchIndex(): void
    {
        config( [
            'cms.disks.public.name' => 'search-relocate-public',
            'cms.disks.private.name' => 'search-relocate-private',
        ] );
        Storage::fake( 'search-relocate-public' );
        Storage::fake( 'search-relocate-private' );
        $file = new File();
        $file->setUniqueIds();
        $path = $file->dir() . '/search-relocate.txt';
        Storage::disk( 'search-relocate-public' )->put( $path, 'search relocation' );

        $file = File::forceCreate( [
            'id' => $file->id,
            'disk' => 'public',
            'mime' => 'text/plain',
            'name' => 'search-relocate.txt',
            'path' => $path,
            'editor' => 'test',
        ] );
        $this->engine->documents = [];

        Files::relocateMany( [$file->id], 'private', $this->user );

        $this->assertArrayNotHasKey( File::class, $this->engine->documents );
        $this->assertSame( 'private', $file->refresh()->disk );
    }
}


class LifecycleSearchEngineSpy extends NullEngine
{
    /** @var array<class-string<Element|File|PageView>, array<string, mixed>> */
    public array $documents = [];


    /**
     * @param class-string<Element|File|PageView> $model
     * @return array<string, mixed>|null
     */
    public function document( string $model ) : ?array
    {
        return $this->documents[$model] ?? null;
    }


    /** @var array<class-string<Element|File|PageView>, list<string>> */
    public array $deleted = [];


    /** @param \Illuminate\Database\Eloquent\Collection<int, Element|File|PageView> $models */
    public function delete( $models ) : void
    {
        foreach( $models as $model ) {
            $this->deleted[get_class( $model )][] = (string) $model->getScoutKey();
        }
    }


    /** @param \Illuminate\Database\Eloquent\Collection<int, Element|File|PageView> $models */
    public function update( $models ) : void
    {
        foreach( $models as $model ) {
            $this->documents[get_class( $model )] = $model->toSearchableArray();
        }
    }
}
