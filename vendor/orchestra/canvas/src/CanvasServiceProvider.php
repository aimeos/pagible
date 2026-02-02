<?php

namespace Orchestra\Canvas;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Orchestra\Canvas\Core\PresetManager;
use Symfony\Component\Yaml\Yaml;

use function Orchestra\Sidekick\join_paths;

class CanvasServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->callAfterResolving(PresetManager::class, static function ($manager, $app) {
            $manager->extend('canvas', static fn ($app) => new GeneratorPreset($app));

            $manager->setDefaultDriver('canvas');
        });

        $this->app->singleton('orchestra.canvas', static function (Application $app) {
            $workingPath = \defined('CANVAS_WORKING_PATH') ? CANVAS_WORKING_PATH : $app->basePath();

            $filesystem = $app->make('files');

            $config = ['preset' => 'laravel'];

            if (file_exists(join_paths($workingPath, 'canvas.yaml'))) {
                $config = Yaml::parseFile(join_paths($workingPath, 'canvas.yaml'));
            } else {
                Arr::set($config, 'testing.extends', [
                    'unit' => 'PHPUnit\Framework\TestCase',
                    'feature' => 'Tests\TestCase',
                ]);

                $config['namespace'] = rescue(fn () => rtrim($app->getNamespace(), '\\'), null, false);
            }

            return Canvas::preset($config, $workingPath);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\GeneratorMakeCommand::class,
                Console\PresetMakeCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides()
    {
        return [
            'orchestra.canvas',
        ];
    }
}
