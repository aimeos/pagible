<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;


abstract class CashierTestAbstract extends \Orchestra\Testbench\TestCase
{
    protected $enablesPackageDiscoveries = false;


    protected function defineEnvironment( $app )
    {
        $app['config']->set( 'database.default', 'testing' );
        $app['config']->set( 'database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
        $app['config']->set( 'auth.providers.users.model', 'App\\Models\\User' );
        $app['config']->set( 'app.previous_keys', [
            'base64:' . base64_encode( 'previous-mollie-webhook-key' ),
        ] );
        $app['config']->set( 'cms.db', 'testing' );

        \Aimeos\Cms\Tenancy::valueUsing( fn() => 'test' );
    }


    protected function getPackageProviders( $app )
    {
        return [
            \Aimeos\Cms\CoreServiceProvider::class,
            \Aimeos\Nestedset\NestedSetServiceProvider::class,
            \Aimeos\Cms\CashierServiceProvider::class,
            \Aimeos\Cms\CashierMollieServiceProvider::class,
        ];
    }


    protected function tearDown(): void
    {
        \Aimeos\Cms\Access::extend( null );
        \Aimeos\Cms\Access::using( null );
        \Aimeos\Cms\Hooks::get()->managed = false;

        parent::tearDown();
    }
}
