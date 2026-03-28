<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;


abstract class AiTestAbstract extends \Orchestra\Testbench\TestCase
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
        $app['config']->set('cms.config.locales', ['en', 'de'] );
        $app['config']->set('scout.driver', 'null');
        $app['config']->set('cms.ai.write', ['provider' => 'gemini', 'model' => 'test', 'api_key' => 'test']);
        $app['config']->set('cms.ai.refine', ['provider' => 'gemini', 'model' => 'test', 'api_key' => 'test']);
        $app['config']->set('cms.ai.describe', ['provider' => 'gemini', 'api_key' => 'test']);
        $app['config']->set('cms.ai.erase', ['provider' => 'clipdrop', 'api_key' => 'test']);
        $app['config']->set('cms.ai.imagine', ['provider' => 'clipdrop', 'api_key' => 'test']);
        $app['config']->set('cms.ai.inpaint', ['provider' => 'stabilityai', 'api_key' => 'test']);
        $app['config']->set('cms.ai.isolate', ['provider' => 'clipdrop', 'api_key' => 'test']);
        $app['config']->set('cms.ai.uncrop', ['provider' => 'clipdrop', 'api_key' => 'test']);
        $app['config']->set('cms.ai.upscale', ['provider' => 'clipdrop', 'api_key' => 'test']);
        $app['config']->set('cms.ai.transcribe', ['provider' => 'openai', 'api_key' => 'test']);
        $app['config']->set('cms.ai.translate', ['provider' => 'deepl', 'api_key' => 'test']);

        $app['config']->set('cms.schemas.content.heading', [
            'group' => 'basic',
            'fields' => [
                'title' => [
                    'type' => 'string',
                    'min' => 1,
                ],
                'level' => [
                    'type' => 'select',
                    'required' => true,
                ],
            ],
        ]);

        \Aimeos\Cms\Tenancy::$callback = function() {
            return 'test';
        };
    }


	protected function getPackageProviders( $app )
	{
		return [
			'Aimeos\Cms\CoreServiceProvider',
			'Aimeos\Cms\AiServiceProvider',
			'Aimeos\Cms\GraphqlServiceProvider',
			'Aimeos\Nestedset\NestedSetServiceProvider',
			'Nuwave\Lighthouse\LighthouseServiceProvider',
		];
	}
}
