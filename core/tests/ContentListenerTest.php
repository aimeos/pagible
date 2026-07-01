<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Events\Saved;
use Aimeos\Cms\Listeners\ContentListener;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Psr\Log\AbstractLogger;


class ContentListenerTest extends TestCase
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
        $logger = new class extends AbstractLogger {
            /**
             * @var array<string, mixed>
             */
            public array $context = [];

            public string $message = '';


            public function log( $level, string|\Stringable $message, array $context = [] ) : void
            {
                if( $level === 'info' ) {
                    $this->message = (string) $message;
                    $this->context = $context;
                }
            }
        };

        Log::shouldReceive( 'channel' )->with( 'cms' )->andReturn( $logger );

        ( new ContentListener )->handle(
            new Saved( 'page', 'id1', 'v1', 'ed', ['path' => 'about', 'domain' => ''], tenant: 'test', source: 'graphql' )
        );

        $this->assertSame( 'cms.page', $logger->message );
        $this->assertSame( 'saved', $logger->context['action'] );
        $this->assertSame( 'page', $logger->context['type'] );
        $this->assertSame( ['id1'], $logger->context['ids'] );
        $this->assertSame( 'ed', $logger->context['editor'] );
        $this->assertSame( 'graphql', $logger->context['source'] );
        $this->assertSame( 'about', $logger->context['path'] );
    }


    public function testNoopWhenChannelUnset() : void
    {
        config( ['cms.watch.channel' => null] );
        Log::shouldReceive( 'channel' )->never();

        ( new ContentListener )->handle( new Saved( 'page', 'id1', 'v1', 'ed', [] ) );

        $this->addToAssertionCount( 1 );
    }


    public function testSwallowsChannelErrors() : void
    {
        $logfile = tempnam( sys_get_temp_dir(), 'cms-watch-' );
        $previous = ini_get( 'error_log' );

        if( $logfile === false ) {
            $this->fail( 'Unable to create temporary error log.' );
        }

        Log::shouldReceive( 'channel' )->andThrow( new \RuntimeException( 'boom' ) );

        ini_set( 'error_log', $logfile );

        try {
            ( new ContentListener )->handle( new Saved( 'page', 'id1', 'v1', 'ed', [] ) );

            $this->assertStringContainsString( 'CMS watch listener error: boom',
                (string) file_get_contents( $logfile ) );
        } finally {
            ini_set( 'error_log', $previous === false ? '' : $previous );
            @unlink( $logfile );
        }
    }
}
