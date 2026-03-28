<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;


abstract class AdminTestAbstract extends \Orchestra\Testbench\TestCase
{
    use InteractsWithViews;
    use WithLaravelMigrations;


    protected ?\App\Models\User $user = null;
    protected $enablesPackageDiscoveries = true;


    protected function defineDatabaseMigrations()
    {
        \Orchestra\Testbench\after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(\Orchestra\Testbench\default_migration_path());
        });
    }


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
        $app['config']->set('cms.db', 'testing');
        $app['config']->set('scout.driver', 'null');

        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'test';
        };
    }


	protected function getPackageProviders( $app )
	{
		return [
			'Aimeos\Cms\CoreServiceProvider',
			'Aimeos\Cms\AdminServiceProvider',
			'Aimeos\Cms\GraphqlServiceProvider',
			'Aimeos\Nestedset\NestedSetServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		];
	}
}
