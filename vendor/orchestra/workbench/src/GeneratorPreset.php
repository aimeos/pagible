<?php

namespace Orchestra\Workbench;

use BadMethodCallException;
use Orchestra\Canvas\Core\Presets\Preset;

use function Orchestra\Sidekick\Filesystem\join_paths;

class GeneratorPreset extends Preset
{
    /**
     * Preset name.
     */
    public function name(): string
    {
        return 'workbench';
    }

    /**
     * Get the path to the base working directory.
     */
    public function basePath(): string
    {
        return rtrim(Workbench::path(), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the source directory.
     */
    public function sourcePath(): string
    {
        return rtrim(Workbench::path('app'), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the testing directory.
     *
     * @throws \BadMethodCallException
     */
    public function testingPath(): never
    {
        throw new BadMethodCallException('Generating test is not supported for [workbench] preset');
    }

    /**
     * Get the path to the resource directory.
     */
    public function resourcePath(): string
    {
        return rtrim(Workbench::path('resources'), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the view directory.
     */
    public function viewPath(): string
    {
        return rtrim(Workbench::path(join_paths('resources', 'views')), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the factory directory.
     */
    public function factoryPath(): string
    {
        return rtrim(Workbench::path(join_paths('database', 'factories')), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the migration directory.
     */
    public function migrationPath(): string
    {
        return rtrim(Workbench::path(join_paths('database', 'migrations')), DIRECTORY_SEPARATOR);
    }

    /**
     * Get the path to the seeder directory.
     */
    public function seederPath(): string
    {
        return rtrim(Workbench::path(join_paths('database', 'seeders')), DIRECTORY_SEPARATOR);
    }

    /**
     * Preset namespace.
     */
    public function rootNamespace(): string
    {
        return Workbench::detectNamespace('app') ?? "Workbench\App\\";
    }

    /**
     * Command namespace.
     */
    public function commandNamespace(): string
    {
        return "{$this->rootNamespace()}Console\\";
    }

    /**
     * Model namespace.
     */
    public function modelNamespace(): string
    {
        return "{$this->rootNamespace()}Models\\";
    }

    /**
     * Provider namespace.
     */
    public function providerNamespace(): string
    {
        return "{$this->rootNamespace()}Providers\\";
    }

    /**
     * Database factory namespace.
     */
    public function factoryNamespace(): string
    {
        return Workbench::detectNamespace('database/factories') ?? "Workbench\Database\Factories\\";
    }

    /**
     * Database seeder namespace.
     */
    public function seederNamespace(): string
    {
        return Workbench::detectNamespace('database/seeders') ?? "Workbench\Database\Seeders\\";
    }

    /**
     * Testing namespace.
     *
     * @throws \BadMethodCallException
     */
    public function testingNamespace(): never
    {
        throw new BadMethodCallException('Generating test is not supported for [workbench] preset');
    }

    /**
     * Preset has custom stub path.
     */
    public function hasCustomStubPath(): bool
    {
        return false;
    }
}
