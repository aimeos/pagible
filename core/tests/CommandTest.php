<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Aimeos\Cms\Jobs\IndexModels;
use App\Models\User;


class CoreCommandTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seeder = TestSeeder::class;

    public function testPublish(): void
    {
        $this->artisan('cms:publish')->assertExitCode( 0 );

        $this->assertEquals( 1, Page::where( 'path', 'hidden' )->firstOrFail()?->status );
        $this->assertEquals( 'Powered by Laravel CMS!', Element::where( 'name', 'Shared footer' )->firstOrFail()?->data->text );
        $this->assertEquals( (object) [
            'en' => 'Test file description',
            'de' => 'Beschreibung der Testdatei',
        ], File::where( 'mime', 'image/jpeg' )->firstOrFail()?->description );
    }


    public function testPublishCollapsesOverdueVersions(): void
    {
        $versions = [];

        Element::withoutSyncingToSearch( function() use ( &$element, &$future, &$versions ) {
            $element = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Before',
                'data' => ['text' => 'Before'], 'editor' => 'test',
            ] );

            foreach( range( 1, 51 ) as $num ) {
                $versions[] = $element->versions()->forceCreate( [
                    'lang' => 'en',
                    'data' => [
                        'lang' => 'en', 'type' => 'text', 'name' => 'After ' . $num,
                        'data' => ['text' => 'After ' . $num],
                    ],
                    'publish_at' => sprintf( '2025-01-01 00:%02d:00', $num ),
                    'editor' => 'test',
                ] )->id;
            }

            $future = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en', 'type' => 'text', 'name' => 'Future',
                    'data' => ['text' => 'Future'],
                ],
                'publish_at' => '2030-01-01 00:00:00',
                'editor' => 'test',
            ] );

            $element->forceFill( ['latest_id' => $future->id] )->saveQuietly();
        } );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertSame( 51, Version::whereIn( 'id', $versions )->where( 'published', true )->count() );
        $this->assertFalse( (bool) $future->fresh()->published );
        $this->assertSame( $future->id, $element->fresh()->latest_id );
        $this->assertSame( 'After 51', $element->fresh()->name );
    }


    public function testPublishDoesNotFallBackAfterFailure(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );
        $ids = [];

        Element::withoutSyncingToSearch( function() use ( &$element, &$ids ) {
            $element = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Before',
                'data' => ['text' => 'Before'], 'editor' => 'test',
            ] );

            foreach( range( 1, 100 ) as $num ) {
                $ids[] = $element->versions()->forceCreate( [
                    'lang' => 'en',
                    'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Older ' . $num],
                    'publish_at' => now()->subMinutes( 2 ),
                    'editor' => 'test',
                ] )->id;
            }

            $ids[] = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Failure'],
                'publish_at' => now()->subMinute(),
                'editor' => 'test',
            ] )->id;
        } );

        Element::saving( fn( $element ) => $element->name === 'Failure'
            ? throw new \RuntimeException( 'Expected publication failure' ) : null );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertSame( 0, Version::whereIn( 'id', $ids )->where( 'published', true )->count() );
        $this->assertSame( 'Before', $element->fresh()->name );
    }


    public function testPublishIndex(): void
    {
        $index = collect( Schema::connection( config( 'cms.db', 'sqlite' ) )->getIndexes( 'cms_versions' ) )
            ->firstWhere( 'name', 'cms_versions_scheduled_index' );

        $this->assertNotNull( $index );
        $this->assertSame( ['tenant_id', 'published', 'publish_at', 'created_at', 'id'], $index['columns'] );
    }


    public function testPublishOrdersEqualSchedulesByCreation(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );

        Element::withoutSyncingToSearch( function() use ( &$element ) {
            $element = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Before',
                'data' => ['text' => 'Before'], 'editor' => 'test',
            ] );
            $at = now()->subMinute();

            $element->versions()->forceCreate( [
                'id' => 'ffffffff-ffff-7fff-bfff-ffffffffffff',
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Older'],
                'publish_at' => $at,
                'created_at' => now()->subMinutes( 3 ),
                'editor' => 'test',
            ] );
            $element->versions()->forceCreate( [
                'id' => '00000000-0000-7000-8000-000000000001',
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Newer'],
                'publish_at' => $at,
                'created_at' => now()->subMinutes( 2 ),
                'editor' => 'test',
            ] );
        } );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertSame( 'Newer', $element->fresh()->name );
    }


    public function testPublishPreservesConcurrentlyRescheduledVersion(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );

        Element::withoutSyncingToSearch( function() use ( &$element, &$newer, &$selected ) {
            $element = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Before',
                'data' => ['text' => 'Before'], 'editor' => 'test',
            ] );
            $selected = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Selected'],
                'publish_at' => now()->subMinutes( 2 ),
                'editor' => 'test',
            ] );
            $newer = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => ['lang' => 'en', 'type' => 'text', 'name' => 'Concurrent'],
                'publish_at' => now()->addHour(),
                'editor' => 'test',
            ] );
            $element->forceFill( ['latest_id' => $newer->id] )->saveQuietly();
        } );

        Element::saving( function( $element ) use ( $newer ) {
            if( $element->name === 'Selected' ) {
                $newer->forceFill( ['publish_at' => now()->subMinute()] )->saveQuietly();
            }
        } );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertTrue( (bool) $selected->fresh()->published );
        $this->assertFalse( (bool) $newer->fresh()->published );
        $this->assertSame( 'Selected', $element->fresh()->name );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertTrue( (bool) $newer->fresh()->published );
        $this->assertSame( 'Concurrent', $element->fresh()->name );
    }


    public function testPublishUsesPortableOrdering(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );

        $page = Page::firstOrFail();
        $element = Element::firstOrFail();
        $file = File::firstOrFail();
        $data = ['lang' => 'en', 'name' => 'Scheduled'];
        $pageVersion = $page->versions()->forceCreate( ['data' => $data, 'publish_at' => now()->subMinute(), 'editor' => 'test'] );
        $elementVersion = $element->versions()->forceCreate( ['data' => $data, 'publish_at' => now()->subMinute(), 'editor' => 'test'] );
        $file = Resource::saveFile( $file->id, ['name' => 'Scheduled'] );
        $fileVersion = $file->latest()->firstOrFail();
        $fileVersion->forceFill( ['publish_at' => now()->subMinute()] )->saveQuietly();
        $page->forceFill( ['latest_id' => $pageVersion->id] )->saveQuietly();
        $element->forceFill( ['latest_id' => $elementVersion->id] )->saveQuietly();

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        $this->assertTrue( (bool) $pageVersion->fresh()->published );
        $this->assertTrue( (bool) $elementVersion->fresh()->published );
        $this->assertTrue( (bool) $fileVersion->fresh()->published );
    }


    public function testPublishQueuesOneSearchBatch(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );
        config( ['scout.queue' => true] );
        Queue::fake();
        $ids = [];

        Element::withoutSyncingToSearch( function() use ( &$ids ) {
            foreach( range( 1, 2 ) as $num )
            {
                $element = Element::forceCreate( [
                    'lang' => 'en', 'type' => 'text', 'name' => 'Before ' . $num,
                    'data' => ['text' => 'Before'], 'editor' => 'test',
                ] );
                $version = $element->versions()->forceCreate( [
                    'lang' => 'en', 'data' => [
                        'lang' => 'en', 'type' => 'text', 'name' => 'After ' . $num,
                        'data' => ['text' => 'After'],
                    ],
                    'publish_at' => now()->subMinute(), 'editor' => 'test',
                ] );
                $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
                $ids[] = $element->id;
            }
        } );

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        Queue::assertPushed( IndexModels::class, 1 );
        Queue::assertPushed( IndexModels::class, fn( $job ) => $job->model === Element::class
            && collect( $job->ids )->sort()->values()->all() === collect( $ids )->sort()->values()->all() );
    }


    public function testPublishSkipsUnchangedRelations(): void
    {
        Version::whereNotNull( 'publish_at' )->update( ['published' => true] );

        $page = Page::firstOrFail();
        $element = Element::firstOrFail();
        $element->latest->forceFill( ['published' => true] )->saveQuietly();
        $version = $page->versions()->forceCreate( [
            'lang' => 'en', 'data' => $page->toArray(),
            'aux' => ['content' => [], 'meta' => [], 'config' => []],
            'publish_at' => now()->subMinute(), 'editor' => 'test',
        ] );
        $version->elements()->attach( $element->id );

        config( ['scout.queue' => true] );
        Queue::fake();

        $this->artisan( 'cms:publish' )->assertExitCode( 0 );

        Queue::assertPushed( IndexModels::class, 1 );
        Queue::assertPushed( IndexModels::class, fn( $job ) => $job->model === Page::class );
    }


    public function testPublishSchedulePreventsOverlap(): void
    {
        $event = collect( $this->app->make( Schedule::class )->events() )->first(
            fn( $event ) => str_contains( (string) $event->command, 'cms:publish' ),
        );

        $this->assertNotNull( $event );
        $this->assertTrue( $event->withoutOverlapping );
        $this->assertTrue( $event->onOneServer );
    }


    public function testUser(): void
    {
        $allPerms = \Aimeos\Cms\Permission::all();
        $imagePerms = array_values( array_filter( $allPerms, fn( $p ) => str_starts_with( $p, 'image:' ) ) );
        $viewPerms = array_values( array_filter( $allPerms, fn( $p ) => str_ends_with( $p, ':view' ) ) );
        $viewPublishPerms = array_values( array_filter( $allPerms, fn( $p ) => str_ends_with( $p, ':view' ) || str_ends_with( $p, ':publish' ) ) );

        $perms = fn() => User::where('email', 'test@example.com')->first()?->cmsperms ?? [];

        $this->artisan('cms:user', ['-p' => 'test', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-e' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $allPerms, $perms() );

        $this->artisan('cms:user', ['-d' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $allPerms, $perms() );

        $this->artisan('cms:user', ['-r' => '*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $imagePerms, $perms() );

        $this->artisan('cms:user', ['-r' => 'image:*', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-a' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $viewPerms, $perms() );

        $this->artisan('cms:user', ['-a' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEqualsCanonicalizing( $viewPublishPerms, $perms() );

        $this->artisan('cms:user', ['-r' => ['*:view', '*:publish'], 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-r' => '*:view', 'email' => 'test@example.com'])->assertExitCode( 0 );
        $this->assertEmpty( $perms() );

        $this->artisan('cms:user', ['-l' => true, 'email' => 'test@example.com'])->assertExitCode( 0 );
    }
}
