<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Added;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;


class ContentEventTest extends CoreTestAbstract
{
    public function testOperationEventBroadcastsToTypeChannel() : void
    {
        $event = new Saved( 'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'], tenant: 'test' );

        $names = array_map( fn( $channel ) => $channel->name, $event->broadcastOn() );

        $this->assertEquals( ['private-cms.test.page'], $names );
    }


    public function testWireNamePerOperation() : void
    {
        $cases = [
            [new Added( 'page', '1', 'v', 'e', [] ), 'page.added'],
            [new Saved( 'page', '1', 'v', 'e', [] ), 'page.saved'],
            [new Published( 'page', '1', 'v', 'e', [] ), 'page.published'],
            [new Restored( 'page', '1', 'v', 'e', [] ), 'page.restored'],
            [new Dropped( 'page', '1', 'v', 'e', [] ), 'page.dropped'],
            [new Moved( 'page', '1', 'v', 'e', [] ), 'page.moved'],
            [new Purged( 'page', '1', 'v', 'e', [] ), 'page.purged'],
        ];

        foreach( $cases as [$event, $name] )
        {
            $this->assertEquals( $name, $event->broadcastAs() );
        }
    }


    public function testPayloadHasNoActionField() : void
    {
        $this->assertArrayNotHasKey( 'action', ( new Saved( 'page', '1', 'v', 'e', [] ) )->broadcastWith() );
    }


    public function testPayloadOmitsAux() : void
    {
        $payload = ( new Saved( 'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'] ) )->broadcastWith();

        $this->assertArrayNotHasKey( 'aux', $payload );
        $this->assertEquals( 'page', $payload['contentType'] );
        $this->assertEquals( ['name' => 'Test'], $payload['data'] );
    }


    public function testContentTypePrefixesNameAndChannel() : void
    {
        foreach( ['page', 'element', 'file'] as $type )
        {
            $saved = new Saved( $type, '1', 'v', 'e', [], tenant: 'test' );
            $this->assertEquals( $type . '.saved', $saved->broadcastAs() );
            $this->assertEquals( ['private-cms.test.' . $type], array_map( fn( $c ) => $c->name, $saved->broadcastOn() ) );
        }
    }


    public function testChannelOmitsEmptyTenant() : void
    {
        $saved = new Saved( 'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'] );

        $this->assertEquals( ['private-cms.page'], array_map( fn( $c ) => $c->name, $saved->broadcastOn() ) );
    }


    public function testCarriesListState() : void
    {
        $event = new Saved(
            'page', '123', 'v1', 'editor@testbench', ['name' => 'Test'],
            true, '2026-06-14 12:00:00', '2026-07-01 08:00:00', '2026-06-14 11:59:00'
        );

        $this->assertTrue( $event->published );
        $this->assertEquals( '2026-06-14 12:00:00', $event->deleted_at );
        $this->assertEquals( '2026-07-01 08:00:00', $event->publish_at );
        $this->assertEquals( '2026-06-14 11:59:00', $event->updated_at );
    }


    public function testDefaultsListState() : void
    {
        $event = new Saved( 'file', '123', 'v1', 'editor@testbench', [] );

        $this->assertFalse( $event->published );
        $this->assertNull( $event->deleted_at );
        $this->assertNull( $event->publish_at );
        $this->assertNull( $event->updated_at );
    }
}
