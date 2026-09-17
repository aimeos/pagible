<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Plugin;
use Aimeos\Cms\WebhookServiceProvider;
use Illuminate\Contracts\Encryption\Encrypter;


class WebhookProviderTest extends WebhookTestAbstract
{
    public function testRegistersPermissionAndAdminPanel() : void
    {
        $this->assertTrue( Permission::has( 'config:webhook' ) );
        $this->assertSame(
            '/vendor/cms/webhooks/WebhookList.js',
            Plugin::all()['panels']['webhooks']['component'] ?? null,
        );
        $this->assertSame(
            '/vendor/cms/webhooks/i18n/{locale}.json',
            Plugin::all()['i18n']['webhooks'] ?? null,
        );
        $this->assertSame(
            'webhooks',
            Plugin::all()['panels']['webhooks']['i18n'] ?? null,
        );
        $this->assertStringContainsString(
            '<svg xmlns="http://www.w3.org/2000/svg" width="1em"',
            Plugin::all()['panels']['webhooks']['icon'] ?? '',
        );
    }


    public function testRejectsNullDeliveryQueue() : void
    {
        config( [
            'queue.default' => 'null',
            'queue.connections.null' => ['driver' => 'null'],
        ] );
        $provider = new class( app() ) extends WebhookServiceProvider {
            public function verifyQueue() : void
            {
                $this->validateQueue();
            }
        };

        $this->expectException( \LogicException::class );
        $this->expectExceptionMessage( 'CMS webhooks require a delivery queue connection.' );
        $provider->verifyQueue();
    }


    public function testRejectsDeliveryWithoutQueueEncryption() : void
    {
        $this->app->instance( Encrypter::class, null );
        $provider = new class( app() ) extends WebhookServiceProvider {
            public function verifyQueue() : void
            {
                $this->validateQueue();
            }
        };

        $this->expectException( \LogicException::class );
        $this->expectExceptionMessage( 'CMS webhooks require Laravel queue encryption support.' );
        $provider->verifyQueue();
    }


    public function testSupportsSynchronousDeliveryQueue() : void
    {
        config( ['queue.default' => 'sync'] );
        $provider = new class( app() ) extends WebhookServiceProvider {
            public function verifyQueue() : void
            {
                $this->validateQueue();
            }
        };

        $this->expectNotToPerformAssertions();
        $provider->verifyQueue();
    }
}
