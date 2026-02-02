<?php

namespace Orchestra\Canvas\Core\Presets;

use function Orchestra\Sidekick\join_paths;

class Laravel extends Preset
{
    /**
     * Preset name.
     */
    public function name(): string
    {
        return 'laravel';
    }

    /**
     * Get the path to the base working directory.
     */
    public function basePath(): string
    {
        return $this->app->basePath();
    }

    /**
     * Get the path to the source directory.
     */
    public function sourcePath(): string
    {
        return $this->app->basePath('app');
    }

    /**
     * Get the path to the testing directory.
     */
    public function testingPath(): string
    {
        return $this->app->basePath('tests');
    }

    /**
     * Get the path to the resource directory.
     */
    public function resourcePath(): string
    {
        return $this->app->resourcePath();
    }

    /**
     * Get the path to the view directory.
     */
    public function viewPath(): string
    {
        return $this->app->make('config')->get('view.paths')[0] ?? $this->app->resourcePath('views');
    }

    /**
     * Get the path to the factory directory.
     */
    public function factoryPath(): string
    {
        return $this->app->databasePath('factories');
    }

    /**
     * Get the path to the migration directory.
     */
    public function migrationPath(): string
    {
        return $this->app->databasePath('migrations');
    }

    /**
     * Get the path to the seeder directory.
     */
    public function seederPath(): string
    {
        if (is_dir($seederPath = $this->app->databasePath('seeds'))) {
            return $seederPath;
        }

        return $this->app->databasePath('seeders');
    }

    /**
     * Preset namespace.
     */
    public function rootNamespace(): string
    {
        return $this->app->getNamespace();
    }

    /**
     * Command namespace.
     */
    public function commandNamespace(): string
    {
        return "{$this->rootNamespace()}Console\Commands\\";
    }

    /**
     * Model namespace.
     */
    public function modelNamespace(): string
    {
        return is_dir(join_paths($this->sourcePath(), 'Models')) ? "{$this->rootNamespace()}Models\\" : $this->rootNamespace();
    }

    /**
     * Provider namespace.
     */
    public function providerNamespace(): string
    {
        return "{$this->rootNamespace()}Providers\\";
    }

    /**
     * Testing namespace.
     */
    public function testingNamespace(): string
    {
        return 'Tests\\';
    }

    /**
     * Database factory namespace.
     */
    public function factoryNamespace(): string
    {
        return 'Database\Factories\\';
    }

    /**
     * Database seeder namespace.
     */
    public function seederNamespace(): string
    {
        return 'Database\Seeders\\';
    }

    /**
     * Preset has custom stub path.
     */
    public function hasCustomStubPath(): bool
    {
        return true;
    }
}
