<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PagesInvalidated;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;


class PagesInvalidatedTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;


    public function testStoresUniqueRoutes(): void
    {
        $event = new PagesInvalidated( [
            ['domain' => 'example.com', 'path' => 'old'],
            ['domain' => 'example.com', 'path' => 'new'],
            ['domain' => 'example.com', 'path' => 'old'],
        ] );

        $this->assertSame( [
            ['domain' => 'example.com', 'path' => 'old'],
            ['domain' => 'example.com', 'path' => 'new'],
        ], $event->routes );
    }


    public function testDispatchesSynchronouslyAfterCommit(): void
    {
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );
        $received = [];
        $tenant = null;

        Event::listen( PagesInvalidated::class, function( PagesInvalidated $event ) use ( &$received, &$tenant ) {
            $received = $event->routes;
            $tenant = $event->tenant;
        } );

        $this->assertSame( 0, $connection->transactionLevel() );
        $connection->beginTransaction();

        try {
            PagesInvalidated::dispatch( [['domain' => '', 'path' => 'committed']] );
            $this->assertSame( [], $received );
            $connection->commit();
        } finally {
            if( $connection->transactionLevel() > 0 ) {
                $connection->rollBack();
            }
        }

        $this->assertSame( [['domain' => '', 'path' => 'committed']], $received );
        $this->assertSame( 'test', $tenant );
    }


    public function testSkipsRolledBackTransactions(): void
    {
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );
        $received = false;

        Event::listen( PagesInvalidated::class, function() use ( &$received ) {
            $received = true;
        } );

        $this->assertSame( 0, $connection->transactionLevel() );
        $connection->beginTransaction();
        PagesInvalidated::dispatch( [['domain' => '', 'path' => 'rolled-back']] );
        $connection->rollBack();

        $this->assertFalse( $received );
    }
}
