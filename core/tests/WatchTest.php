<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\ContentChanged;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;


/**
 * Verifies that content events reach in-process listeners (used by the watch package) even
 * when websocket broadcasting is disabled, without being sent to the broadcaster.
 */
class WatchTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


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


    public function testSaveDispatchesSavedToListenerWithoutBroadcast() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = [];
        Event::listen( Saved::class, function( Saved $e ) use ( &$captured ) {
            $captured[] = $e;
        } );

        $id = $this->id( $page );

        Resource::savePage( $id, ['title' => 'Renamed'], $this->user );

        $this->assertCount( 1, $captured );
        $this->assertSame( 'page', $captured[0]->contentType );
        $this->assertSame( $id, $captured[0]->id );
        $this->assertSame( 'editor@testbench', $captured[0]->editor );
        $this->assertSame( 'Renamed', $captured[0]->data['title'] ?? null );
    }


    public function testListenerPathDoesNotBroadcast() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = null;
        Event::listen( Saved::class, function( Saved $e ) use ( &$captured ) {
            $captured = $e;
        } );

        Resource::savePage( $this->id( $page ), ['title' => 'X'], $this->user );

        $this->assertNotNull( $captured );
        $this->assertFalse( $captured->broadcasting );
        $this->assertFalse( $captured->broadcastWhen() );
    }


    public function testContentMetricListenerDoesNotRequireSavedListener() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = [];
        Event::listen( ContentChanged::class, function( ContentChanged $e ) use ( &$captured ) {
            $captured[] = $e;
        } );

        $this->assertFalse( Event::hasListeners( Saved::class ) );

        Resource::savePage( $this->id( $page ), ['title' => 'Renamed'], $this->user );

        $this->assertCount( 1, $captured );
        $this->assertSame( 'page', $captured[0]->contentType );
        $this->assertSame( 'saved', $captured[0]->action );
        $this->assertSame( 'cli', $captured[0]->source );
        $this->assertSame( 'test', $captured[0]->tenant );
    }


    public function testWatchDispatchEmitsForRegisteredListenerWithoutWatchChannel() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $captured = null;
        Event::listen( ContentChanged::class, function( ContentChanged $e ) use ( &$captured ) {
            $captured = $e;
        } );

        Watch::dispatchWhen( 'cms.theme.watch', ContentChanged::class, fn() => new ContentChanged(
            contentType: 'page',
            action: 'searched',
            source: 'web',
            tenant: 'test',
            value: 3,
        ) );

        $this->assertInstanceOf( ContentChanged::class, $captured );
        $this->assertSame( 'page', $captured->contentType );
        $this->assertSame( 'searched', $captured->action );
    }


    public function testWatchDispatchSkippedWhenNothingListensAndWatchOff() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $built = false;

        Watch::dispatchWhen( 'cms.theme.watch', ContentChanged::class, function() use ( &$built ) {
            $built = true;
            return new ContentChanged( contentType: 'page', action: 'searched', source: 'web', tenant: 'test' );
        } );

        $this->assertFalse( $built );
    }


    public function testWatchStartRunsForRegisteredListenerWithoutWatchChannel() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        Event::listen( ContentChanged::class, fn() => null );

        $start = Watch::start( 'cms.theme.watch', ContentChanged::class );

        $this->assertNotNull( $start );
        $this->assertGreaterThan( 0, $start );
        $this->assertNull( Watch::start( 'cms.theme.watch', Saved::class ) );
    }


    public function testFireDispatchesEventRegardlessOfWatchState() : void
    {
        // fire() has no gate of its own: the caller decides whether to dispatch.
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $captured = null;
        Event::listen( ContentChanged::class, function( ContentChanged $e ) use ( &$captured ) {
            $captured = $e;
        } );

        Watch::fire( fn() => new ContentChanged( contentType: 'page', action: 'viewed', source: 'web', tenant: 'test' ) );

        $this->assertInstanceOf( ContentChanged::class, $captured );
        $this->assertSame( 'viewed', $captured->action );
    }


    public function testFireSwallowsFactoryErrors() : void
    {
        // Watch must never break the request, so a throwing factory is caught.
        Watch::fire( function() {
            throw new \RuntimeException( 'boom' );
        } );

        $this->expectNotToPerformAssertions();
    }


    public function testBulkDispatchesBulkToListener() : void
    {
        $page1 = $this->page();
        $page2 = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = [];
        Event::listen( Bulk::class, function( Bulk $e ) use ( &$captured ) {
            $captured[] = $e;
        } );

        $id1 = $this->id( $page1 );
        $id2 = $this->id( $page2 );

        Resource::bulkPage( [$id1, $id2], ['title' => 'Renamed'], $this->user );

        $this->assertCount( 1, $captured );
        $this->assertSame( 'page', $captured[0]->contentType );
        $this->assertCount( 2, $captured[0]->ids );
        $this->assertContains( $id1, $captured[0]->ids );
        $this->assertFalse( $captured[0]->broadcasting );
    }


    public function testPurgeDispatchesPurgedToListener() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = [];
        Event::listen( Purged::class, function( Purged $e ) use ( &$captured ) {
            $captured[] = $e;
        } );

        $id = $this->id( $page );

        Resource::purge( Page::class, [$id], 'editor@testbench' );

        $this->assertCount( 1, $captured );
        $this->assertSame( $id, $captured[0]->id );
    }


    public function testNoEventWhenNothingListensAndBroadcastOff() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Moved::class] );

        Resource::movePage( $this->id( $page ), parent: $this->id( $this->root() ), user: $this->user );

        Event::assertNotDispatched( Moved::class );
    }


    protected function page() : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'watch-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: $this->id( $this->root() ) );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }


    private function id( Page $page ) : string
    {
        if( !is_string( $page->id ) ) {
            throw new \RuntimeException( 'Page ID is missing.' );
        }

        return $page->id;
    }
}
