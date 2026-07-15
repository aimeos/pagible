<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;


class McpWatchTest extends McpTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        config( ['cms.watch.channel' => 'cms'] );

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    public function testReadToolDispatchesObserved() : void
    {
        Event::fake( [Observed::class] );
        $page = $this->home();

        CmsServer::actingAs( $this->editor() )->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'mcp'
            && $e->action === 'get-page'
            && $e->dimensions['success'] === true
            && $e->durationMs >= 0.0
        );
    }


    public function testWriteToolDispatchesObserved() : void
    {
        Event::fake( [Observed::class] );
        $page = $this->home();

        CmsServer::actingAs( $this->editor() )->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'title' => 'Updated Title',
        ] );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'mcp'
            && $e->action === 'save-page'
            && $e->dimensions['success'] === true
        );
    }


    public function testFailedToolDispatchesUnsuccessfulObserved() : void
    {
        Event::fake( [Observed::class] );

        CmsServer::actingAs( $this->editor() )->tool( \Aimeos\Cms\Tools\GetPage::class, [] );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'mcp'
            && $e->action === 'get-page'
            && $e->dimensions['success'] === false
        );
    }


    public function testSavePageTaggedWithMcpSource() : void
    {
        config( ['cms.broadcast' => false] );
        $page = $this->home();

        // The originating interface is captured onto the event; assert it carries 'mcp'.
        $captured = [];
        Event::listen( Saved::class, function( Saved $e ) use ( &$captured ) {
            $captured[] = ['id' => $e->id, 'source' => $e->source];
        } );

        CmsServer::actingAs( $this->editor() )->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'title' => 'Updated Title',
        ] );

        $this->assertCount( 1, $captured );
        $this->assertSame( $page->id, $captured[0]['id'] );
        $this->assertSame( 'mcp', $captured[0]['source'] );
    }


    protected function editor() : \App\Models\User
    {
        if( !$this->user instanceof \App\Models\User ) {
            throw new \RuntimeException( 'Test user is not initialized.' );
        }

        return $this->user;
    }


    protected function home() : Page
    {
        return Page::where( 'name', 'Home' )->firstOrFail();
    }
}
