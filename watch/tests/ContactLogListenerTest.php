<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Listeners\ContactLogListener;
use Aimeos\Cms\WatchServiceProvider;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class ContactLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, WatchServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testAnonymizesPii() : void
    {
        config( ['cms.watch.anonymize' => true] );

        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'info' )->once()->with( 'cms.contact', \Mockery::on( fn( $ctx ) =>
            $ctx['email'] === hash_hmac( 'sha256', 'sender@google.com', (string) config( 'app.key' ) )
            && $ctx['ip'] === hash_hmac( 'sha256', '127.0.0.1', (string) config( 'app.key' ) )
            && $ctx['email'] !== 'sender@google.com'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new ContactLogListener )->handle( new Contacted( 'sender@google.com', '127.0.0.1' ) );
    }
}
