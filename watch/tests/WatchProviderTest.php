<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\WatchServiceProvider;
use Monolog\Formatter\JsonFormatter;
use Orchestra\Testbench\TestCase;


class WatchProviderTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class, WatchServiceProvider::class];
    }


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'cms.watch.channel', 'cms' );
    }


    public function testConfigDefaultsMerged() : void
    {
        $this->assertSame( 1.0, config( 'cms.watch.sample' ) );
        $this->assertTrue( config( 'cms.watch.anonymize' ) );
    }


    public function testRegistersLogChannelWhenEnabled() : void
    {
        $this->assertSame( 'daily', config( 'logging.channels.cms.driver' ) );
        $this->assertSame( JsonFormatter::class, config( 'logging.channels.cms.formatter' ) );
        $this->assertSame( 14, config( 'logging.channels.cms.days' ) );
    }


    public function testSubscribesContentListener() : void
    {
        $this->assertTrue( \Illuminate\Support\Facades\Event::hasListeners( \Aimeos\Cms\Events\Saved::class ) );
        $this->assertTrue( \Illuminate\Support\Facades\Event::hasListeners( \Aimeos\Cms\Events\Bulk::class ) );
    }


    public function testDoesNotOverrideExistingChannel() : void
    {
        config( ['logging.channels.custom' => ['driver' => 'single', 'path' => '/tmp/x.log']] );
        config( ['cms.watch.channel' => 'custom'] );

        // re-run the provider boot logic by resolving a fresh provider
        ( new WatchServiceProvider( $this->app ) )->boot();

        $this->assertSame( 'single', config( 'logging.channels.custom.driver' ) );
    }
}
