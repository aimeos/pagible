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
        $app['config']->set( 'cms.db', 'testing' );

        \Aimeos\Cms\Tenancy::$callback = fn() => 'test';
    }


    protected function getPackageProviders( $app )
    {
        return [
            \Aimeos\Cms\CoreServiceProvider::class,
            \Aimeos\Nestedset\NestedSetServiceProvider::class,
            \Aimeos\Cms\CashierServiceProvider::class,
            \Laravel\Cashier\CashierServiceProvider::class,
            \Aimeos\Cms\CashierStripeServiceProvider::class,
        ];
    }


    protected function tearDown(): void
    {
        \Aimeos\Cms\Access::extend( null );
        \Aimeos\Cms\Access::using( null );
        ( new \ReflectionProperty( \Aimeos\Cms\Tenancy::class, 'managed' ) )->setValue( null, false );

        parent::tearDown();
    }
}
