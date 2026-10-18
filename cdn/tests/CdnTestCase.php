<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\CdnServiceProvider;
use Aimeos\Cms\CoreServiceProvider;
use Orchestra\Testbench\TestCase;


abstract class CdnTestCase extends TestCase
{
    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'app.url', 'https://example.com' );
        $app['config']->set( 'queue.default', 'database' );
        $app['config']->set( 'cms.cdn.delay', 0 );
        $app['config']->set( 'cms.cdn.clients', [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']],
        ] );
    }


    protected function getPackageProviders( $app )
    {
        return [
            CoreServiceProvider::class,
            CdnServiceProvider::class,
        ];
    }
}
