<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Plugin;


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
}
