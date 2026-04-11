<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
        $app['config']->set('database.connections.testing', [
            'driver'   => env('DB_DRIVER', 'sqlite'),
            'host'     => env('DB_HOST', ''),
            'port'     => env('DB_PORT', ''),
            'database' => env('DB_DATABASE', ':memory:'),
            'username' => env('DB_USERNAME', ''),
            'password' => env('DB_PASSWORD', ''),
        ]);

        $app['config']->set('auth.providers.users.model', 'App\\Models\\User');
        $app['config']->set('scout.driver', 'collection');
        $app['config']->set('cms.db', 'testing');

        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'test';
        };
    }


	protected function getPackageProviders( $app )
	{
		return [
			'Aimeos\Cms\CoreServiceProvider',
			'Aimeos\Nestedset\NestedSetServiceProvider',
		];
	}
}
