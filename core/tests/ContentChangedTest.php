<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Events\ContentChanged;


class ContentChangedTest extends CoreTestAbstract
{
    public function testBroadcastsToItemAndTypeChannels() : void
    {
        $event = new ContentChanged( 'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'], ['content' => []] );

        $names = array_map( fn( $channel ) => $channel->name, $event->broadcastOn() );

        // per-item channel for the open detail view + per-type channel for the list/tree views
        $this->assertEquals( ['private-cms.page.123', 'private-cms.page'], $names );
    }


    public function testBroadcastName() : void
    {
        $event = new ContentChanged( 'file', '123', 'v1', 'editor@testbench', [] );

        $this->assertEquals( 'content.changed', $event->broadcastAs() );
    }


    public function testStructuralChangesBroadcastToTypeChannelOnly() : void
    {
        foreach( ['added', 'removed', 'moved'] as $action )
        {
            $event = new ContentChanged( 'page', '123', 'v1', 'editor@testbench', [], action: $action );

            $names = array_map( fn( $channel ) => $channel->name, $event->broadcastOn() );

            // structural changes refresh only the list/tree, not an open detail view
            $this->assertEquals( ['private-cms.page'], $names );
        }
    }


    public function testCarriesListState() : void
    {
        $event = new ContentChanged(
            'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'], null,
            true, '2026-06-14 12:00:00', '2026-07-01 08:00:00', '2026-06-14 11:59:00'
        );

        $this->assertTrue( $event->published );
        $this->assertEquals( '2026-06-14 12:00:00', $event->deleted_at );
        $this->assertEquals( '2026-07-01 08:00:00', $event->publish_at );
        $this->assertEquals( '2026-06-14 11:59:00', $event->updated_at );
    }


    public function testDefaultsListState() : void
    {
        $event = new ContentChanged( 'file', '123', 'v1', 'editor@testbench', [] );

        $this->assertFalse( $event->published );
        $this->assertNull( $event->deleted_at );
        $this->assertNull( $event->publish_at );
        $this->assertNull( $event->updated_at );
    }
}
