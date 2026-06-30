<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CoreServiceProvider;
use Aimeos\Cms\Watch;
use Monolog\Formatter\JsonFormatter;
use Orchestra\Testbench\TestCase;


class WatchProviderTest extends TestCase
{
    protected function getPackageProviders( $app )
    {
        return [CoreServiceProvider::class];
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

        Watch::registerChannel();

        $this->assertSame( 'single', config( 'logging.channels.custom.driver' ) );
    }


    public function testFieldsAddsRequestId() : void
    {
        $id = Watch::fields( [] )['request_id'];

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id
        );
    }


    public function testFieldsKeepsRequestIdStable() : void
    {
        $this->assertSame( Watch::fields( [] )['request_id'], Watch::fields( [] )['request_id'] );
    }


    public function testFieldsUsesInboundRequestId() : void
    {
        request()->headers->set( 'X-Request-Id', 'req-abc.123' );

        $this->assertSame( 'req-abc.123', Watch::fields( [] )['request_id'] );
    }


    public function testFieldsSanitizesInboundRequestId() : void
    {
        request()->headers->set( 'X-Request-Id', "valid-id\r\ninjected line" );

        $id = Watch::fields( [] )['request_id'];

        $this->assertSame( 'valid-idinjectedline', $id );
        $this->assertStringNotContainsString( "\n", $id );
    }
}
