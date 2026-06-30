<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Listeners\BulkListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class BulkListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testWritesBulkEntry() : void
    {
        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.element', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'bulk'
            && $ctx['type'] === 'element'
            && $ctx['source'] === 'graphql'
            && $ctx['ids'] === ['a', 'b']
            && $ctx['editor'] === 'ed'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new BulkListener )->handle(
            new Bulk( 'element', ['a', 'b'], ['a' => 'v1', 'b' => 'v2'], ['lang' => 'de'], 'ed', 'test', 'graphql' )
        );
    }
}
