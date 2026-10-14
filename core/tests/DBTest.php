<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\DB;
use Illuminate\Support\Facades\Cache;


class DBTest extends CoreTestAbstract
{
    public function testTransactionReturnsResult(): void
    {
        $this->assertSame( 'result', DB::transaction( fn() => 'result' ) );
    }


    public function testLockedTransactionBlocksUsingConfiguredLifetime(): void
    {
        config( ['cms.lock' => 7] );

        $lock = \Mockery::mock( \Illuminate\Contracts\Cache\Lock::class );
        $lock->shouldReceive( 'block' )
            ->once()
            ->with( 7, \Mockery::type( \Closure::class ) )
            ->andReturnUsing( fn( int $seconds, \Closure $callback ) => $callback() );

        Cache::shouldReceive( 'lock' )
            ->once()
            ->with( 'cms_pages_test', 7 )
            ->andReturn( $lock );

        $this->assertSame( 'result', DB::lockedTransaction( fn() => 'result' ) );
    }
}
