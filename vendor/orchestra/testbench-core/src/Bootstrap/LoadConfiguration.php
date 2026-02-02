<?php

namespace Orchestra\Testbench\Bootstrap;

use Generator;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Orchestra\Sidekick\Env;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

use function Orchestra\Testbench\default_skeleton_path;
use function Orchestra\Testbench\package_path;
use function Orchestra\Testbench\uses_default_skeleton;

/**
 * @internal
 *
 * @phpstan-type TLaravel \Illuminate\Contracts\Foundation\Application
 */
class LoadConfiguration
{
    /**
     * Cached Laravel Framework default configuration.
     *
     * @var \Illuminate\Contracts\Config\Repository|null
     */
    protected static ?RepositoryContract $cachedFrameworkConfigurations = null;

    /**
     * Bootstrap the given application.
     *
     * @param  TLaravel  $app
     * @return void
     */
    public function bootstrap(Application $app): void
    {
        $app->instance('config', $config = new Repository([]));

        $this->loadConfigurationFiles($app, $config);

        if (\is_null($config->get('database.connections.testing'))) {
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'foreign_key_constraints' => Env::get('DB_FOREIGN_KEYS', false),
            ]);
        }

        $this->configureDefaultDatabaseConnection($config);

        mb_internal_encoding('UTF-8');
    }

    /**
     * Load the configuration items from all of the files.
     *
     * @param  TLaravel  $app
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    private function loadConfigurationFiles(Application $app, RepositoryContract $config): void
    {
        $shouldMerge = method_exists($app, 'shouldMergeFrameworkConfiguration')
            ? $app->shouldMergeFrameworkConfiguration()
            : true;

        static::$cachedFrameworkConfigurations ??= new Repository(
            (new Collection(uses_default_skeleton($app->basePath()) ? [] : $this->getFrameworkDefaultConfigurations()))
                ->transform(fn ($path, $key) => require $path)
                ->all()
        );

        $this->extendsLoadedConfiguration(
            (new LazyCollection(function () use ($app) {
                $path = $this->getConfigurationPath($app);

                if (\is_string($path)) {
                    yield from $this->getConfigurationsFromPath($path);
                }
            }))
                ->collect()
                ->transform(fn ($path, $key) => $this->resolveConfigurationFile($path, $key))
        )->each(static function ($path, $key) use ($config) {
            $config->set($key, require $path);
        })->when($shouldMerge === true, static function ($configurations) use ($config) {
            /** @var \Illuminate\Contracts\Config\Repository $baseConfigurations */
            $baseConfigurations = static::$cachedFrameworkConfigurations;

            /** @var array<int, string> $excludes */
            $excludes = $configurations->keys()->all();

            (new Collection($baseConfigurations->all()))->reject(
                fn ($data, $key) => \in_array($key, $excludes)
            )->each(function ($data, $key) use ($config) {
                $config->set($key, $data);
            });

            return $configurations->each(static function ($data, $key) use ($config, $baseConfigurations) {
                foreach (static::mergeableOptions($key) as $option) {
                    $name = "{$key}.{$option}";

                    $config->set($name, array_merge(($baseConfigurations->get($name) ?? []), ($config->get($name) ?? [])));
                }
            });
        });
    }

    /**
     * Get the configuration file nesting path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $configPath
     * @return string
     */
    protected function getNestedDirectory(SplFileInfo $file, string $configPath): string
    {
        $directory = $file->getPath();

        if ($nested = trim(str_replace($configPath, '', $directory), DIRECTORY_SEPARATOR)) {
            $nested = str_replace(DIRECTORY_SEPARATOR, '.', $nested).'.';
        }

        return $nested;
    }

    /**
     * Resolve the configuration file.
     *
     * @param  string  $path
     * @param  string  $key
     * @return string
     */
    protected function resolveConfigurationFile(string $path, string $key): string
    {
        return $path;
    }

    /**
     * Extend the loaded configuration.
     *
     * @param  \Illuminate\Support\Collection  $configurations
     * @return \Illuminate\Support\Collection
     */
    protected function extendsLoadedConfiguration(Collection $configurations): Collection
    {
        return $configurations;
    }

    /**
     * Configure the default database connection.
     *
     * @param  \Illuminate\Contracts\Config\Repository  $config
     * @return void
     */
    protected function configureDefaultDatabaseConnection(RepositoryContract $config): void
    {
        if ($config->get('database.default') === 'sqlite' && ! is_file($config->get('database.connections.sqlite.database'))) {
            $config->set('database.default', 'testing');
        }
    }

    /**
     * Get the application configuration path.
     *
     * @param  TLaravel  $app
     * @return string
     *
     * @throws \RuntimeException
     */
    protected function getConfigurationPath(Application $app): string
    {
        $configurationPath = is_dir($app->basePath('config'))
            ? $app->basePath('config')
            : default_skeleton_path('config');

        if ($configurationPath === false) {
            throw new RuntimeException('Unable to locate configuration path');
        }

        return $configurationPath;
    }

    /**
     * Get the framework default configurations.
     *
     * @return array<string, string>
     *
     * @codeCoverageIgnore
     */
    protected function getFrameworkDefaultConfigurations(): array
    {
        return (new LazyCollection(function () {
            yield from $this->getConfigurationsFromPath(package_path(['vendor', 'laravel', 'framework', 'config']));
        }))->all();
    }

    /**
     * Get the configurations from path.
     *
     * @param  string  $path
     * @return \Generator
     */
    protected function getConfigurationsFromPath(string $path): Generator
    {
        foreach (Finder::create()->files()->name('*.php')->in($path) as $file) {
            $directory = $this->getNestedDirectory($file, $path);

            yield $directory.basename($file->getRealPath(), '.php') => $file->getRealPath();
        }
    }

    /**
     * Get the options within the configuration file that should be merged again.
     *
     * @param  string  $name
     * @return array<int, string>
     */
    public static function mergeableOptions(string $name): array
    {
        return [
            'auth' => ['guards', 'providers', 'passwords'],
            'broadcasting' => ['connections'],
            'cache' => ['stores'],
            'database' => ['connections'],
            'filesystems' => ['disks'],
            'logging' => ['channels'],
            'mail' => ['mailers'],
            'queue' => ['connections'],
        ][$name] ?? [];
    }
}
