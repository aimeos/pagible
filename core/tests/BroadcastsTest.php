<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\ContentType;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Change;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Jobs\HandleChanges;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Facades\Elements;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Utils;
use Aimeos\Cms\VariantAction;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;


class BroadcastsTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ]);
    }


    public function testSaveBroadcastsSaved() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Saved::class] );

        Pages::save( $page->id, ['title' => 'Renamed'], $this->user );

        Event::assertDispatched( Saved::class, fn( Saved $e ) =>
            $e->contentType === 'page'
            && $e->type() === ContentType::Page
            && $e->id === $page->id
            && $e->editor === 'editor@testbench'
            && $e->published === false
            && $e->tenant === 'test'
            && ( $e->data['title'] ?? null ) === 'Renamed'
        );
    }


    public function testBulkBroadcastsOneBulkNotPerItem() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Saved::class, Bulk::class] );

        Pages::bulk( [$page1->id, $page2->id], ['title' => 'Renamed'], $this->user );

        // the per-item "saved" broadcasts are coalesced into a single "bulk" event
        Event::assertNotDispatched( Saved::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) =>
            $e->contentType === 'page'
            && count( $e->ids ) === 2
            && in_array( $page1->id, $e->ids )
            && $e->editor === 'editor@testbench'
            && $e->tenant === 'test'
            && ( $e->data['title'] ?? null ) === 'Renamed'
            && ( $e->data['published'] ?? null ) === false
            && !empty( $e->data['updated_at'] )
            && ( $e->latest[$page1->id] ?? null ) === PageView::withTrashed()->find( $page1->id )->latest_id
        );
    }


    public function testBulkBroadcastsNothingWhenDisabled() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Saved::class, Bulk::class] );

        Pages::bulk( [$page->id], ['title' => 'Renamed'], $this->user );

        // faked events are dispatched to the fake but never broadcast
        Event::assertNotDispatched( Saved::class );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => !$e->broadcastWhen() );
    }


    public function testBulkPublishBroadcastsOneBulkEvent() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Pages::publishMany( [$page1->id, $page2->id], $this->user );

        Event::assertNotDispatched( Published::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'published'
            && $e->type() === ContentType::Page && count( $e->ids ) === 2
            && ( $e->data['published'] ?? null ) === true );
    }


    public function testPublishBroadcastsRootAndDependenciesOnce() : void
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => false] );
        $element = Elements::add( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Dependency',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Pages::publishMany( [$page->id], $this->user );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatchedTimes( Published::class, 3 );

        foreach( [$page->id, $element->id, $file->id] as $id ) {
            Event::assertDispatched( Published::class, fn( Published $event ) => $event->id === $id );
        }
    }


    public function testPublishBroadcastsOnlyUnpublishedItems() : void
    {
        $published = $this->page();
        $draft = $this->page();

        Pages::publishMany( [$published->id], $this->user );

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Pages::publishMany( [$published->id, $draft->id], $this->user );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatchedTimes( Published::class, 1 );
        Event::assertDispatched( Published::class, fn( Published $event ) => $event->id === $draft->id );
    }


    public function testPublishCarriesSupersededVersionsInBulkEvent() : void
    {
        $pages = collect( [$this->page(), $this->page()] );
        $published = [];

        foreach( $pages as $page ) {
            $version = $page->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en', 'name' => 'Published',
                    'path' => 'published-route', 'domain' => 'published.example',
                ],
                'editor' => 'editor@testbench',
                'published' => true,
            ] );
            $published[$page->variant_id] = ['version_id' => (string) $version->id];
        }

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Base::announceMany( $pages, ContentAction::Published, 'editor@testbench', [
            'published' => true,
            'publish_at' => null,
        ], projected: $published );

        Event::assertNotDispatched( Published::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $event ) =>
            $event->projected === $pages->mapWithKeys( fn( PageView $page ) => [
                $page->id => $published[$page->variant_id]['version_id'],
            ] )->all()
            && !array_key_exists( 'projected', $event->broadcastWith() )
        );
    }


    public function testBulkLifecycleBroadcastsOneEventPerAction() : void
    {
        $pages = [$this->page(), $this->page()];
        $ids = collect( $pages )->pluck( 'id' )->all();
        config( ['cms.broadcast' => true] );

        Event::fake( [Dropped::class, Bulk::class] );
        Pages::dropMany( $ids, $this->user );
        Event::assertNotDispatched( Dropped::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'dropped'
            && count( $e->ids ) === 2 && !empty( $e->data['deleted_at'] ) );

        Event::fake( [Restored::class, Bulk::class] );
        Pages::restoreMany( $ids, $this->user );
        Event::assertNotDispatched( Restored::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'restored'
            && array_key_exists( 'deleted_at', $e->data ) && $e->data['deleted_at'] === null );

        Event::fake( [Purged::class, Bulk::class] );
        Pages::purgeMany( $ids, $this->user );
        Event::assertNotDispatched( Purged::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'purged'
            && count( $e->ids ) === 2 && $e->broadcastAs() === 'page.purged' && $e->langs === [] );
    }


    public function testMoveBroadcastsMoved() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Moved::class] );

        Pages::move( $page->id, $this->user, parent: $this->root()->id );

        Event::assertDispatched( Moved::class );
    }


    public function testMoveBroadcastsAfterTreeLockIsReleased() : void
    {
        $page = $this->page();
        $released = false;
        config( ['cache.default' => 'array', 'cms.broadcast' => false] );

        Event::listen( Moved::class, function() use ( &$released ) {
            $lock = Cache::lock( 'cms_pages_test', 30 );
            $released = $lock->get();

            if( $released ) {
                $lock->release();
            }
        } );

        Pages::move( $page->id, $this->user, parent: $this->root()->id );

        $this->assertTrue( $released );
    }


    public function testPurgeBroadcastsPurged() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Purged::class] );

        Pages::purgeMany( [$page->id], $this->user );

        Event::assertDispatched( Purged::class );
    }


    public function testSingleFilePurgeBroadcastsPurged() : void
    {
        $file = File::forceCreate( [
            'lang' => 'en', 'mime' => 'text/plain', 'name' => 'purged.txt',
            'path' => '', 'editor' => 'editor@testbench',
        ] );
        $version = $file->versions()->forceCreate( [
            'lang' => 'en', 'editor' => 'editor@testbench',
            'data' => ['path' => '', 'previews' => []],
        ] );
        $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        config( ['cms.broadcast' => true] );
        Event::fake( [Purged::class, Bulk::class] );

        Files::purgeMany( [$file->id], $this->user );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatched( Purged::class, fn( Purged $event ) =>
            $event->contentType === 'file'
            && $event->id === $file->id
            && $event->latestId === $version->id
        );
    }


    public function testLifecycleAnnouncementUsesLatestDraftPageRoute() : void
    {
        $page = $this->page();
        $id = (string) $page->id;
        Pages::publishMany( [$id], $this->user );
        Pages::save( $id, [
            'path' => 'draft-route',
            'domain' => 'draft.example',
        ], $this->user );
        $events = [];

        config( ['cms.broadcast' => false] );

        foreach( [Dropped::class, Restored::class] as $class ) {
            Event::listen( $class, function( $event ) use ( &$events ) {
                $events[] = $event;
            } );
        }

        Pages::dropMany( [$id], $this->user );
        Pages::restoreMany( [$id], $this->user );

        $this->assertCount( 2, $events );

        foreach( $events as $event ) {
            $this->assertSame( ['path' => 'draft-route', 'domain' => 'draft.example'], $event->data );
        }
    }


    public function testElementLifecycleAnnouncementOmitsVersionData() : void
    {
        $element = Element::query()->with( 'latest' )->firstOrFail();
        $captured = null;

        $this->assertNotEmpty( (array) $element->latest?->data );
        config( ['cms.broadcast' => false] );
        Event::listen( Dropped::class, function( Dropped $event ) use ( &$captured ) {
            $captured = $event;
        } );

        Elements::dropMany( [$element->id], $this->user );

        $this->assertInstanceOf( Dropped::class, $captured );
        $this->assertSame( [], $captured->data );
    }


    public function testChangeListenerSingleAndBulk() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        $page3 = $this->page();
        $changes = [];

        config( ['cms.broadcast' => false] );
        Change::listen( ContentAction::Published, function( Change $change ) use ( &$changes ) {
            $changes[] = $change;
        } );

        Pages::publish( $page1->id, $this->user );
        Pages::publishMany( [$page2->id, $page3->id], $this->user );
        Pages::save( $page1->id, ['title' => 'Not published'], $this->user );

        // the bulk changes are in database order, which differs between the database engines
        $bulk = collect( array_slice( $changes, 1 ) )->keyBy( 'id' );

        $this->assertCount( 3, $changes );
        $this->assertSame( $page1->id, $changes[0]->id );
        $this->assertEqualsCanonicalizing( [$page2->id, $page3->id], $bulk->keys()->all() );
        $this->assertTrue( collect( $changes )->every( fn( Change $c ) => $c->action === 'published' && $c->published && $c->type() === ContentType::Page ) );
        $this->assertSame( (string) PageView::findOrFail( $page2->id )->latest_id, $bulk[$page2->id]->versionId );
        $this->assertSame( 'editor@testbench', $changes[0]->editor );
    }


    public function testChangeListenerBulkSaved() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        $changes = [];

        config( ['cms.broadcast' => false] );
        Change::listen( ['saved', 'dropped'], function( Change $change ) use ( &$changes ) {
            $changes[] = $change->action . ':' . $change->id;
        } );

        Pages::bulk( [$page1->id, $page2->id], ['title' => 'Bulk'], $this->user );
        Pages::drop( $page1->id, $this->user );

        $this->assertEquals( ['saved:' . $page1->id, 'saved:' . $page2->id, 'dropped:' . $page1->id], $changes );
    }


    public function testChangeListenerClass() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        ChangeRecorder::$changes = [];

        config( ['cms.broadcast' => false] );
        Change::listen( 'saved', ChangeRecorder::class );

        Pages::save( $page1->id, ['title' => 'Single'], $this->user );
        Pages::bulk( [$page1->id, $page2->id], ['title' => 'Bulk'], $this->user );

        $this->assertEquals( [$page1->id, $page1->id, $page2->id], array_map( fn( Change $c ) => $c->id, ChangeRecorder::$changes ) );
    }


    public function testChangeListenerQueued() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        QueuedChangeRecorder::$changes = [];

        config( ['cms.broadcast' => false] );
        Change::listen( ContentAction::Published, QueuedChangeRecorder::class );
        Queue::fake( [HandleChanges::class] );

        Pages::publishMany( [$page1->id, $page2->id], $this->user );
        Pages::save( $page1->id, ['title' => 'Not published'], $this->user );

        Queue::assertPushed( HandleChanges::class, 1 );
        Queue::assertPushedOn( 'changes', HandleChanges::class, function( HandleChanges $job ) use ( $page1, $page2 ) {
            return $job->listener === QueuedChangeRecorder::class
                && array_map( fn( Change $c ) => $c->id, $job->changes ) === [$page1->id, $page2->id];
        } );
        $this->assertSame( [], QueuedChangeRecorder::$changes );

        Queue::pushed( HandleChanges::class )->first()->handle();

        $this->assertCount( 2, QueuedChangeRecorder::$changes );
    }


    public function testChangeListenerAllActions() : void
    {
        $page = $this->page();
        $changes = [];

        config( ['cms.broadcast' => false] );
        Change::listen( '*', function( Change $change ) use ( &$changes ) {
            $changes[] = $change->action;
        } );

        Pages::save( $page->id, ['title' => 'All'], $this->user );
        Pages::publish( $page->id, $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::ignoreMany( [$page->id], 'de', $this->user );
        Pages::drop( $page->id, $this->user );

        $this->assertEquals( ['saved', 'published', 'added', 'ignored', 'dropped'], $changes );
    }


    public function testChangeListenerVariantAction() : void
    {
        $page = $this->page();
        $langs = [];

        config( ['cms.broadcast' => false] );
        Change::listen( VariantAction::Ignored, function( Change $change ) use ( &$langs ) {
            $langs[] = $change->lang;
        } );

        Variants::add( $page->id, 'de', $this->user );
        Variants::ignoreMany( [$page->id], 'de', $this->user );

        $this->assertEquals( ['de'], $langs );
    }


    public function testChangeListenerRemoved() : void
    {
        $page = $this->page();
        $count = 0;

        config( ['cms.broadcast' => false] );
        $stop = Change::listen( 'saved', function() use ( &$count ) {
            $count++;
        } );

        Pages::save( $page->id, ['title' => 'First'], $this->user );
        $stop();
        Pages::save( $page->id, ['title' => 'Second'], $this->user );

        $this->assertEquals( 1, $count );
    }


    public function testChangeListenerUnknownAction() : void
    {
        $this->expectException( \InvalidArgumentException::class );
        Change::listen( 'publish', fn() => null );
    }


    public function testChangesOfSingleEvent() : void
    {
        $event = new Published( 'page', 'p1', 'v1', 'editor', ['path' => 'draft', 'lang' => 'en'], projection: [
            'version_id' => 'v0', 'path' => 'live', 'domain' => 'example.com', 'lang' => 'de',
        ] );
        $change = $event->changes()[0];

        $this->assertSame( ['published', 'v0', true, 'live', 'example.com', 'de'],
            [$change->action, $change->versionId, $change->published, $change->path, $change->domain, $change->lang] );

        $change = ( new Dropped( 'page', 'p1', 'v1', 'editor', ['path' => 'old', 'lang' => 'en'] ) )->changes()[0];

        $this->assertSame( ['dropped', 'v1', false, 'old', null], [$change->action, $change->versionId, $change->published, $change->path, $change->lang] );
    }


    public function testChangesOfBulkEvent() : void
    {
        $event = new Bulk( 'page', ['p1', 'p2'], ['p1' => 'v1', 'p2' => 'v2'], ['title' => 'x'], 'editor', langs: ['p2' => 'de'] );
        $changes = $event->changes();

        $this->assertSame( ['saved', 'saved'], [$changes[0]->action, $changes[1]->action] );
        $this->assertSame( ['v1', 'v2'], [$changes[0]->versionId, $changes[1]->versionId] );
        $this->assertSame( [null, 'de'], [$changes[0]->lang, $changes[1]->lang] );
        $this->assertFalse( $changes[0]->published );
    }


    public function testNothingBroadcastWhenDisabled() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Saved::class] );

        Pages::save( $page->id, ['title' => 'Renamed'], $this->user );

        // faked events are dispatched to the fake but never broadcast
        Event::assertDispatched( Saved::class, fn( Saved $e ) => !$e->broadcastWhen() );
    }


    protected function page( array $content = [] ) : PageView
    {
        return Pages::add( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'bc-' . Utils::uid(),
            'content' => $content,
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : PageView
    {
        return PageView::where( 'tag', 'root' )->firstOrFail();
    }
}


class ChangeRecorder
{
    /** @var list<Change> */
    public static array $changes = [];

    public function handle( Change $change ) : void
    {
        self::$changes[] = $change;
    }
}


class QueuedChangeRecorder implements ShouldQueue
{
    /** @var list<Change> */
    public static array $changes = [];

    public string $queue = 'changes';

    public function handle( Change $change ) : void
    {
        self::$changes[] = $change;
    }
}
