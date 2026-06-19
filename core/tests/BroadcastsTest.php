<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
