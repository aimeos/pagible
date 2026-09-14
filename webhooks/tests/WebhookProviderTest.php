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
    }


    public function testRejectsSynchronousDeliveryQueue() : void
    {
        config( ['queue.default' => 'sync'] );
        $provider = new class( app() ) extends WebhookServiceProvider {
            public function verifyQueue() : void
            {
                $this->validateQueue();
            }
        };

        $this->expectException( \LogicException::class );
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
}
