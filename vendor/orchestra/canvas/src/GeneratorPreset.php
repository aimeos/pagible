<?php

namespace Orchestra\Canvas;

use Orchestra\Canvas\Core\Presets\Preset;

use function Orchestra\Sidekick\join_paths;

class GeneratorPreset extends Preset
{
    /**
     * Preset name.
     */
    public function name(): string
    {
        return 'canvas';
    }

    /**
     * Get the path to the base working directory.
     */
    public function basePath(): string
    {
        return $this->canvas()->basePath();
    }

    /**
     * Get the path to the source directory.
     */
    public function sourcePath(): string
    {
        return $this->canvas()->sourcePath();
    }

    /**
     * Get the path to the testing directory.
     */
    public function testingPath(): string
    {
        return $this->canvas()->testingPath();
    }

    /**
     * Get the path to the resource directory.
     */
    public function resourcePath(): string
    {
        return $this->canvas()->resourcePath();
    }

    /**
     * Get the path to the view directory.
     */
    public function viewPath(): string
    {
        return join_paths($this->resourcePath(), 'views');
    }

    /**
     * Get the path to the factory directory.
     */
    public function factoryPath(): string
    {
        return $this->canvas()->factoryPath();
    }

    /**
     * Get the path to the migration directory.
     */
    public function migrationPath(): string
    {
        return $this->canvas()->migrationPath();
    }

    /**
     * Get the path to the seeder directory.
     */
    public function seederPath(): string
    {
        return $this->canvas()->seederPath();
    }

    /**
     * Preset namespace.
     */
    public function rootNamespace(): string
    {
        return $this->canvas()->rootNamespace().'\\';
    }

    /**
     * Command namespace.
     */
    public function commandNamespace(): string
    {
        return $this->canvas()->commandNamespace().'\\';
    }

    /**
     * Model namespace.
     */
    public function modelNamespace(): string
    {
        return $this->canvas()->modelNamespace().'\\';
    }

    /**
     * Provider namespace.
     */
    public function providerNamespace(): string
    {
        return $this->canvas()->providerNamespace().'\\';
    }

    /**
     * Database factory namespace.
     */
    public function factoryNamespace(): string
    {
        return $this->canvas()->factoryNamespace().'\\';
    }

    /**
     * Database seeder namespace.
     */
    public function seederNamespace(): string
    {
        return $this->canvas()->seederNamespace().'\\';
    }

    /**
     * Testing namespace.
     */
    public function testingNamespace(): string
    {
        return $this->canvas()->testingNamespace().'\\';
    }

    /**
     * Preset has custom stub path.
     */
    public function hasCustomStubPath(): bool
    {
        return ! \is_null($this->canvas()->getCustomStubPath());
    }

    /** {@inheritDoc} */
    #[\Override]
    public function userProviderModel(?string $guard = null): ?string
    {
        if (\is_null($guard) || $guard === $this->app->make('config')->get('auth.defaults.guard')) {
            return $this->canvas()->config('user-auth-model')
                ?? $this->canvas()->config('user-auth-provider')
                ?? parent::userProviderModel($guard);
        }

        return parent::userProviderModel($guard);
    }

    /**
     * Get canvas preset.
     */
    public function canvas(): Presets\Preset
    {
        return $this->app->make('orchestra.canvas');
    }
}
