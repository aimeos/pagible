<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;


class McpWatchTest extends McpTestAbstract
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
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    protected function tearDown(): void
    {
        Resource::$source = 'cli';
        parent::tearDown();
    }


    public function testSavePageTaggedWithMcpSource() : void
    {
        config( ['cms.broadcast' => false] );
        $page = Page::where( 'name', 'Home' )->first();

        // The originating interface is captured onto the event; assert it carries 'mcp'.
        $captured = [];
        Event::listen( Saved::class, function( Saved $e ) use ( &$captured ) {
            $captured[] = ['id' => $e->id, 'source' => $e->source];
        } );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'title' => 'Updated Title',
        ] );

        $this->assertCount( 1, $captured );
        $this->assertSame( $page->id, $captured[0]['id'] );
        $this->assertSame( 'mcp', $captured[0]['source'] );
    }
}
