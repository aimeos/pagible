<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\CmsGraphql;
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


    public function testWatchDispatchEmitsForRegisteredListenerWithoutWatchChannel() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $captured = null;
        Event::listen( CmsGraphql::class, function( CmsGraphql $e ) use ( &$captured ) {
            $captured = $e;
        } );

        Watch::dispatchWhen( 'cms.theme.watch', CmsGraphql::class, fn() => new CmsGraphql(
            action: 'pages',
            tenant: 'test',
        ) );

        $this->assertInstanceOf( CmsGraphql::class, $captured );
        $this->assertSame( 'pages', $captured->action );
    }


    public function testWatchDispatchSkippedWhenNothingListensAndWatchOff() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $built = false;

        Watch::dispatchWhen( 'cms.theme.watch', CmsGraphql::class, function() use ( &$built ) {
            $built = true;
            return new CmsGraphql( action: 'pages', tenant: 'test' );
        } );

        $this->assertFalse( $built );
    }


    public function testWatchStartRunsForRegisteredListenerWithoutWatchChannel() : void
    {
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        Event::listen( CmsGraphql::class, fn() => null );

        $start = Watch::start( 'cms.theme.watch', CmsGraphql::class );

        $this->assertNotNull( $start );
        $this->assertGreaterThan( 0, $start );
        $this->assertNull( Watch::start( 'cms.theme.watch', Saved::class ) );
    }


    public function testFireDispatchesEventRegardlessOfWatchState() : void
    {
        // fire() has no gate of its own: the caller decides whether to dispatch.
        config( ['cms.watch.channel' => null, 'cms.theme.watch' => false] );

        $captured = null;
        Event::listen( CmsGraphql::class, function( CmsGraphql $e ) use ( &$captured ) {
            $captured = $e;
        } );

        Watch::fire( fn() => new CmsGraphql( action: 'page', tenant: 'test' ) );

        $this->assertInstanceOf( CmsGraphql::class, $captured );
        $this->assertSame( 'page', $captured->action );
    }


    public function testFireSwallowsFactoryErrors() : void
    {
        // Watch must never break the request, so a throwing factory is caught.
        $logfile = tempnam( sys_get_temp_dir(), 'cms-watch-' );
        $previous = ini_get( 'error_log' );

        if( $logfile === false ) {
            $this->fail( 'Unable to create temporary error log.' );
        }

        ini_set( 'error_log', $logfile );

        try {
            Watch::fire( function() {
                throw new \RuntimeException( 'boom' );
            } );

            $this->assertStringContainsString( 'CMS watch event error: boom',
                (string) file_get_contents( $logfile ) );
        } finally {
            ini_set( 'error_log', $previous === false ? '' : $previous );
            @unlink( $logfile );
        }
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
