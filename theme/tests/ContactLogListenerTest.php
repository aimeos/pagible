<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\CmsContact;
use Aimeos\Cms\Listeners\ContactLogListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\AbstractLogger;


class ContactLogListenerTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testAnonymizesPii() : void
    {
        config( ['cms.watch.anonymize' => true] );

        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{level: mixed, message: string, context: array<string, mixed>}>
             */
            public array $entries = [];


            public function log( mixed $level, string|\Stringable $message, array $context = [] ) : void
            {
                $this->entries[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new ContactLogListener )->handle( new CmsContact( 'sender@google.com', '127.0.0.1' ) );

        $this->assertSame( 'cms.contact', $logger->entries[0]['message'] );
        $this->assertSame(
            hash_hmac( 'sha256', 'sender@google.com', (string) config( 'app.key' ) ),
            $logger->entries[0]['context']['email']
        );
        $this->assertSame(
            hash_hmac( 'sha256', '127.0.0.1', (string) config( 'app.key' ) ),
            $logger->entries[0]['context']['ip']
        );
        $this->assertNotSame( 'sender@google.com', $logger->entries[0]['context']['email'] );
    }
}
