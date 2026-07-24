<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

        Resource::drop( Element::class, [(string) $element->id], $this->user );

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

        Resource::drop( File::class, [(string) $file->id], $this->user );
        $this->engine->documents = [];
        Resource::restore( File::class, [(string) $file->id], $this->user );

        $document = $this->engine->document( File::class );

        $this->assertNotNull( $document );
        $this->assertSame( $expected, $document['draft'] );
        $this->assertSame( $latest->editor, $document['editor'] );
        $this->assertSame( $latest->data?->mime, $document['mime'] );
    }


    public function testPublishIndexesPublishedVersionsSynchronously(): void
    {
        $file = File::query()->where( 'mime', 'image/jpeg' )->firstOrFail();
        Publication::publish( File::class, [(string) $file->id], $this->user );

        $this->assertTrue( $this->engine->document( File::class )['published'] ?? false );

        $element = Element::query()->firstOrFail();
        Publication::publish( Element::class, [(string) $element->id], $this->user );

        $this->assertTrue( $this->engine->document( Element::class )['published'] ?? false );

        $page = Page::query()->where( 'path', 'hidden' )->firstOrFail();
        Resource::savePage( (string) $page->id, ['title' => 'Publish search test'], $this->user );
        Publication::publish( Page::class, [(string) $page->id], $this->user );

        $this->assertTrue( $this->engine->document( Page::class )['published'] ?? false );
    }
}


class LifecycleSearchEngineSpy extends NullEngine
{
    /** @var array<class-string<Element|File|Page>, array<string, mixed>> */
    public array $documents = [];


    /**
     * @param class-string<Element|File|Page> $model
     * @return array<string, mixed>|null
     */
    public function document( string $model ) : ?array
    {
        return $this->documents[$model] ?? null;
    }


    /** @param \Illuminate\Database\Eloquent\Collection<int, Element|File|Page> $models */
    public function update( $models ) : void
    {
        foreach( $models as $model ) {
            $this->documents[get_class( $model )] = $model->toSearchableArray();
        }
    }
}
