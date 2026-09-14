<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;


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


    public function testUnknownActionThrows() : void
    {
        $this->expectException( \InvalidArgumentException::class );

        ( new Page )->announce( 'bogus' );
    }


    public function testSaveBroadcastsSaved() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Saved::class] );

        Resource::savePage( $page->id, ['title' => 'Renamed'], $this->user );

        Event::assertDispatched( Saved::class, fn( Saved $e ) =>
            $e->contentType === 'page'
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

        Resource::bulkPage( [$page1->id, $page2->id], ['title' => 'Renamed'], $this->user );

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
            && ( $e->latest[$page1->id] ?? null ) === Page::withTrashed()->find( $page1->id )->latest_id
        );
    }


    public function testBulkBroadcastsNothingWhenDisabled() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Saved::class, Bulk::class] );

        Resource::bulkPage( [$page->id], ['title' => 'Renamed'], $this->user );

        Event::assertNotDispatched( Saved::class );
        Event::assertNotDispatched( Bulk::class );
    }


    public function testBulkPublishBroadcastsOneBulkEvent() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Publication::publish( Page::class, [$page1->id, $page2->id], $this->user );

        Event::assertNotDispatched( Published::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'published'
            && $e->contentType === 'page' && count( $e->ids ) === 2
            && ( $e->data['published'] ?? null ) === true );
    }


    public function testPublishBroadcastsRootAndDependenciesOnce() : void
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => false] );
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Dependency',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Publication::publish( Page::class, [$page->id], $this->user );

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

        Publication::publish( Page::class, [$published->id], $this->user );

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Publication::publish( Page::class, [$published->id, $draft->id], $this->user );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatchedTimes( Published::class, 1 );
        Event::assertDispatched( Published::class, fn( Published $event ) => $event->id === $draft->id );
    }


    public function testPublishBroadcastsSupersededVersionsIndividually() : void
    {
        $pages = collect( [$this->page(), $this->page()] );
        $published = [];

        foreach( $pages as $page ) {
            $published[$page->id] = $page->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en', 'name' => 'Published',
                    'path' => 'published-route', 'domain' => 'published.example',
                ],
                'editor' => 'editor@testbench',
                'published' => true,
            ] );
        }

        config( ['cms.broadcast' => true] );
        Event::fake( [Published::class, Bulk::class] );

        Base::announceMany( $pages, 'published', 'editor@testbench', [
            'published' => true,
            'publish_at' => null,
        ], projected: $published );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatchedTimes( Published::class, 2 );

        foreach( $pages as $page ) {
            Event::assertDispatched( Published::class, fn( Published $event ) =>
                $event->id === $page->id
                && $event->latest_id === $page->latest_id
                && $event->projection === [
                    'version_id' => $published[$page->id]->id,
                    'path' => 'published-route',
                    'domain' => 'published.example',
                ]
                && $event->published === false
            );
        }
    }


    public function testBulkLifecycleBroadcastsOneEventPerAction() : void
    {
        $pages = [$this->page(), $this->page()];
        $ids = collect( $pages )->pluck( 'id' )->all();
        config( ['cms.broadcast' => true] );

        Event::fake( [Dropped::class, Bulk::class] );
        Resource::drop( Page::class, $ids, $this->user );
        Event::assertNotDispatched( Dropped::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'dropped'
            && count( $e->ids ) === 2 && !empty( $e->data['deleted_at'] ) );

        Event::fake( [Restored::class, Bulk::class] );
        Resource::restore( Page::class, $ids, $this->user );
        Event::assertNotDispatched( Restored::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'restored'
            && array_key_exists( 'deleted_at', $e->data ) && $e->data['deleted_at'] === null );

        Event::fake( [Purged::class, Bulk::class] );
        Resource::purge( Page::class, $ids, $this->user );
        Event::assertNotDispatched( Purged::class );
        Event::assertDispatchedTimes( Bulk::class, 1 );
        Event::assertDispatched( Bulk::class, fn( Bulk $e ) => $e->action === 'purged'
            && count( $e->ids ) === 2 && $e->broadcastAs() === 'page.purged' );
    }


    public function testMoveBroadcastsMoved() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Moved::class] );

        Resource::movePage( $page->id, parent: $this->root()->id, user: $this->user );

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

        Resource::movePage( $page->id, parent: $this->root()->id, user: $this->user );

        $this->assertTrue( $released );
    }


    public function testPurgeBroadcastsPurged() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Purged::class] );

        Resource::purge( Page::class, [$page->id], $this->user );

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

        Resource::purge( File::class, [$file->id], $this->user );

        Event::assertNotDispatched( Bulk::class );
        Event::assertDispatched( Purged::class, fn( Purged $event ) =>
            $event->contentType === 'file'
            && $event->id === $file->id
            && $event->latest_id === $version->id
        );
    }


    public function testLifecycleAnnouncementUsesLatestDraftPageRoute() : void
    {
        $page = $this->page();
        $id = (string) $page->id;
        Publication::publish( Page::class, [$id], $this->user );
        Resource::savePage( $id, [
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

        Resource::drop( Page::class, [$id], $this->user );
        Resource::restore( Page::class, [$id], $this->user );

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

        Resource::drop( Element::class, [$element->id], $this->user );

        $this->assertInstanceOf( Dropped::class, $captured );
        $this->assertSame( [], $captured->data );
    }


    public function testNothingBroadcastWhenDisabled() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Saved::class] );

        Resource::savePage( $page->id, ['title' => 'Renamed'], $this->user );

        Event::assertNotDispatched( Saved::class );
    }


    protected function page( array $content = [] ) : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'bc-' . Utils::uid(),
            'content' => $content,
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
