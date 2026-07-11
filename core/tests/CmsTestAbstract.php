<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;


abstract class CmsTestAbstract extends \Orchestra\Testbench\TestCase
{
    use InteractsWithViews;


    protected ?\App\Models\User $user = null;
    protected $enablesPackageDiscoveries = true;


    protected function defineEnvironment( $app )
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => env('DB_DRIVER', 'sqlite'),
            'host'     => env('DB_HOST', ''),
            'port'     => env('DB_PORT', ''),
            'database' => env('DB_DRIVER', 'sqlite') === 'sqlite' ? ':memory:' : env('DB_DATABASE', ''),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
        ]);

        $app['config']->set('auth.providers.users.model', 'App\\Models\\User');
        $app['config']->set('scout.driver', 'collection');
        // Pulse rescues missing-storage errors, which leaves PostgreSQL test transactions aborted.
        $app['config']->set('pulse.enabled', false);
        $app['config']->set('cms.db', 'testing');

        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'test';
        };
    }


    protected function tearDown(): void
    {
        ( new \ReflectionProperty( \Aimeos\Cms\Schema::class, 'themes' ) )->setValue( null, [] );
        parent::tearDown();
    }


    protected function getPackageProviders( $app )
    {
        return [
            'Aimeos\Cms\CoreServiceProvider',
            'Aimeos\Nestedset\NestedSetServiceProvider',
        ];
    }
}
