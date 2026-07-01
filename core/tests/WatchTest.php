<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
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


    public function testSaveDispatchesSavedToListenerWithoutBroadcast() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );

        $captured = [];
        Event::listen( Saved::class, function( Saved $e ) use ( &$captured ) {
            $captured[] = $e;
        } );

        Resource::savePage( $page->id, ['title' => 'Renamed'], $this->user );

        $this->assertCount( 1, $captured );
        $this->assertSame( 'page', $captured[0]->contentType );
        $this->assertSame( $page->id, $captured[0]->id );
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

        Resource::savePage( $page->id, ['title' => 'X'], $this->user );

        $this->assertNotNull( $captured );
        $this->assertFalse( $captured->broadcasting );
        $this->assertFalse( $captured->broadcastWhen() );
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

        Resource::bulkPage( [$page1->id, $page2->id], ['title' => 'Renamed'], $this->user );

        $this->assertCount( 1, $captured );
        $this->assertSame( 'page', $captured[0]->contentType );
        $this->assertCount( 2, $captured[0]->ids );
        $this->assertContains( $page1->id, $captured[0]->ids );
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

        Resource::purge( Page::class, [$page->id], 'editor@testbench' );

        $this->assertCount( 1, $captured );
        $this->assertSame( $page->id, $captured[0]->id );
    }


    public function testNoEventWhenNothingListensAndBroadcastOff() : void
    {
        $page = $this->page();
        config( ['cms.broadcast' => false] );
        Event::fake( [Moved::class] );

        Resource::movePage( $page->id, parent: $this->root()->id, user: $this->user );

        Event::assertNotDispatched( Moved::class );
    }


    protected function page() : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'watch-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
