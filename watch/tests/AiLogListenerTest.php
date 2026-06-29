<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Listeners\AiLogListener;
use Aimeos\Cms\WatchServiceProvider;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class AiLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, WatchServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testSanitizesAndTruncatesError() : void
    {
        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.ai', \Mockery::on( function( $ctx ) {
            return $ctx['mutation'] === 'write'
                && $ctx['success'] === false
                && !str_contains( $ctx['error'], 'sk-secret123' )
                && str_contains( $ctx['error'], '[REDACTED]' )
                && mb_strlen( $ctx['error'] ) <= 200;
        } ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new AiLogListener )->handle( new Generated(
            mutation: 'write',
            provider: 'openai',
            success: false,
            error: 'Auth failed for key sk-secret123 ' . str_repeat( 'x', 300 ),
        ) );
    }


    public function testNoopWhenChannelUnset() : void
    {
        config( ['cms.watch.channel' => null] );
        Log::shouldReceive( 'channel' )->never();

        ( new AiLogListener )->handle( new Generated( mutation: 'write' ) );

        $this->assertTrue( true );
    }
}
