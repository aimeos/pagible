<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\UserChanged;
use Aimeos\Cms\Listeners\UserLogListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;


class UserLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
        $app['config']->set( 'cms.watch.anonymize', true );
    }


    public function testWritesWarningKeepingPrincipalsIdentifiable() : void
    {
        $logger = \Mockery::mock( LoggerInterface::class );
        $logger->shouldReceive( 'warning' )->once()->with( 'cms.user', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'permission'
            // Principals stay identifiable for forensic use even with anonymization on
            && $ctx['actor'] === 'admin@example.com'
            && $ctx['target'] === 'member@example.com'
            && $ctx['target_id'] === '42'
            && $ctx['assignments'] === ['viewer']
            // Network metadata still follows the anonymization setting
            && $ctx['ip'] === hash_hmac( 'sha256', '127.0.0.1', (string) config( 'app.key' ) )
            && $ctx['tenant_id'] === 'test'
        ) );
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new UserLogListener )->handle( new UserChanged(
            action: 'permission',
            actorEmail: 'admin@example.com',
            targetEmail: 'member@example.com',
            targetId: '42',
            assignments: ['viewer'],
            ip: '127.0.0.1',
            tenant: 'test',
        ) );
    }


    public function testFallsBackToDefaultChannelWhenWatchChannelUnavailable() : void
    {
        // The audit of a permission grant must never be silently dropped just
        // because no dedicated watch channel is configured.
        config()->set( 'cms.watch.channel', null );

        Log::shouldReceive( 'warning' )->once()->with( 'cms.user', \Mockery::on( fn( $ctx ) =>
            $ctx['action'] === 'permission'
            && $ctx['actor'] === 'admin@example.com'
            && $ctx['target'] === 'member@example.com'
        ) );

        ( new UserLogListener )->handle( new UserChanged(
            action: 'permission',
            actorEmail: 'admin@example.com',
            targetEmail: 'member@example.com',
            targetId: '42',
            assignments: ['viewer'],
            ip: '127.0.0.1',
            tenant: 'test',
        ) );
    }
}
