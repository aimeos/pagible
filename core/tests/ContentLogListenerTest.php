<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Listeners\ContentLogListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class ContentLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testWritesStructuredEntryForSaved() : void
    {
        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.page', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'saved'
            && $ctx['type'] === 'page'
            && $ctx['ids'] === ['id1']
            && $ctx['editor'] === 'ed'
            && $ctx['source'] === 'graphql'
            && $ctx['path'] === 'about'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new ContentLogListener )->handle(
            new Saved( 'page', 'id1', 'v1', 'ed', ['path' => 'about', 'domain' => ''], tenant: 'test', source: 'graphql' )
        );
    }


    public function testWritesBulkEntry() : void
    {
        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.element', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'bulk' && $ctx['ids'] === ['a', 'b'] && $ctx['editor'] === 'ed'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new ContentLogListener )->handleBulk(
            new Bulk( 'element', ['a', 'b'], ['a' => 'v1', 'b' => 'v2'], ['lang' => 'de'], 'ed', 'test' )
        );
    }


    public function testNoopWhenChannelUnset() : void
    {
        config( ['cms.watch.channel' => null] );
        Log::shouldReceive( 'channel' )->never();

        ( new ContentLogListener )->handle( new Saved( 'page', 'id1', 'v1', 'ed', [] ) );

        $this->assertTrue( true );
    }


    public function testSwallowsChannelErrors() : void
    {
        Log::shouldReceive( 'channel' )->andThrow( new \RuntimeException( 'boom' ) );

        ( new ContentLogListener )->handle( new Saved( 'page', 'id1', 'v1', 'ed', [] ) );

        $this->assertTrue( true );
    }
}
