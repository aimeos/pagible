<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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


    public function testMoveBroadcastsMoved() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Moved::class] );

        Resource::movePage( $page->id, parent: $this->root()->id, user: $this->user );

        Event::assertDispatched( Moved::class );
    }


    public function testPurgeBroadcastsPurged() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => true] );
        Event::fake( [Purged::class] );

        Resource::purge( Page::class, [$page->id], 'editor@testbench' );

        Event::assertDispatched( Purged::class );
    }


    public function testLifecycleAnnouncementsLoadVersionsOncePerOperation() : void
    {
        $first = $this->page();
        $second = $this->page();
        $routes = [
            $first->id => ['path' => $first->path, 'domain' => $first->domain],
            $second->id => ['path' => $second->path, 'domain' => $second->domain],
        ];
        $events = [];
        $queries = [];

        config( ['cms.broadcast' => false] );

        foreach( [Dropped::class, Restored::class, Purged::class] as $class ) {
            Event::listen( $class, function( $event ) use ( &$events ) {
                $events[] = $event;
            } );
        }

        DB::listen( function( QueryExecuted $query ) use ( &$queries ) {
            if( str_contains( $query->sql, 'cms_versions' ) ) {
                $queries[] = $query->sql;
            }
        } );

        $ids = [$first->id, $second->id];
        Resource::drop( Page::class, $ids, 'editor@testbench' );
        Resource::restore( Page::class, $ids, 'editor@testbench' );
        Resource::purge( Page::class, $ids, 'editor@testbench' );

        $this->assertCount( 3, $queries );
        $this->assertCount( 6, $events );

        foreach( $events as $event ) {
            $this->assertSame( $routes[$event->id], $event->data );
        }
    }


    public function testLifecycleAnnouncementsUseLatestDraftPageRoute() : void
    {
        $page = $this->page();
        $id = (string) $page->id;
        Resource::publish( Page::class, [$id], 'editor@testbench' );
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

        Resource::drop( Page::class, [$id], 'editor@testbench' );
        Resource::restore( Page::class, [$id], 'editor@testbench' );

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

        Resource::drop( Element::class, [$element->id], 'editor@testbench' );

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


    protected function page() : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'bc-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
