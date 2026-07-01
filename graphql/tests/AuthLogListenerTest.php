<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Listeners\AuthLogListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class AuthLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testAnonymizesEmail() : void
    {
        config( ['cms.watch.anonymize' => true] );

        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.auth', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'login'
            && $ctx['email'] === hash_hmac( 'sha256', 'editor@testbench', (string) config( 'app.key' ) )
            && $ctx['email'] !== 'editor@testbench'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new AuthLogListener )->handle( new Authed( 'login', 'editor@testbench', '127.0.0.1' ) );
    }


    public function testKeepsRawPiiWhenAnonymizeOff() : void
    {
        config( ['cms.watch.anonymize' => false] );

        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.auth', \Mockery::on( fn( $ctx ) =>
            $ctx['email'] === 'editor@testbench' && $ctx['ip'] === '127.0.0.1'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new AuthLogListener )->handle( new Authed( 'login', 'editor@testbench', '127.0.0.1' ) );
    }
}
