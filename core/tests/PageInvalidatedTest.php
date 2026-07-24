<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;


class PageInvalidatedTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;


    public function testCapturesPaths(): void
    {
        $pages = new PageInvalidated( 'example.com', ['about', 'contact'] );

        $this->assertSame( 'example.com', $pages->domain );
        $this->assertSame( ['about', 'contact'], $pages->paths );
        $this->assertSame( 'test', $pages->tenant );
    }


    public function testDispatchesSynchronouslyAfterCommit(): void
    {
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );
        $received = null;

        Event::listen( PageInvalidated::class, function( PageInvalidated $event ) use ( &$received ) {
            $received = [$event->tenant, $event->domain, $event->paths];
        } );

        $this->assertSame( 0, $connection->transactionLevel() );
        $connection->beginTransaction();

        try {
            PageInvalidated::dispatch( '', ['committed'] );
            $this->assertNull( $received );
            $connection->commit();
        } finally {
            if( $connection->transactionLevel() > 0 ) {
                $connection->rollBack();
            }
        }

        $this->assertSame( ['test', '', ['committed']], $received );
    }


    public function testSkipsRolledBackTransactions(): void
    {
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );
        $received = false;

        Event::listen( PageInvalidated::class, function() use ( &$received ) {
            $received = true;
        } );

        $this->assertSame( 0, $connection->transactionLevel() );
        $connection->beginTransaction();
        PageInvalidated::dispatch( '', ['rolled-back'] );
        $connection->rollBack();

        $this->assertFalse( $received );
    }
}
