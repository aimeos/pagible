<?php

namespace Orchestra\Canvas\Core\Presets;

use Illuminate\Contracts\Foundation\Application;
use LogicException;

abstract class Preset
{
    /**
     * Construct a new preset.
     *
     * @return void
     */
    public function __construct(
        protected Application $app
    ) {}

    /**
     * Check if preset name equal to $name.
     */
    public function is(string $name): bool
    {
        return $this->name() === $name;
    }

    /**
     * Get the model for the default guard's user provider.
     *
     * @return class-string|null
     *
     * @throws \LogicException
     */
    public function userProviderModel(?string $guard = null): ?string
    {
        /** @var \Illuminate\Contracts\Config\Repository $config */
        $config = $this->app->make('config');

        $guard = $guard ?: $config->get('auth.defaults.guard');

        if (\is_null($provider = $config->get("auth.guards.{$guard}.provider"))) {
            throw new LogicException(\sprintf('The [%s] guard is not defined in your "auth" configuration file.', $guard));
        }

        return $config->get("auth.providers.{$provider}.model");
    }

    /**
     * Preset name.
     */
    abstract public function name(): string;

    /**
     * Get the path to the base working directory.
     */
    abstract public function basePath(): string;

    /**
     * Get the path to the source directory.
     */
    abstract public function sourcePath(): string;

    /**
     * Get the path to the testing directory.
     */
    abstract public function testingPath(): string;

    /**
     * Get the path to the resource directory.
     */
    abstract public function resourcePath(): string;

    /**
     * Get the path to the view directory.
     */
    abstract public function viewPath(): string;

    /**
     * Get the path to the factory directory.
     */
    abstract public function factoryPath(): string;

    /**
     * Get the path to the migration directory.
     */
    abstract public function migrationPath(): string;

    /**
     * Get the path to the seeder directory.
     */
    abstract public function seederPath(): string;

    /**
     * Preset namespace.
     */
    abstract public function rootNamespace(): string;

    /**
     * Command namespace.
     */
    abstract public function commandNamespace(): string;

    /**
     * Model namespace.
     */
    abstract public function modelNamespace(): string;

    /**
     * Provider namespace.
     */
    abstract public function providerNamespace(): string;

    /**
     * Testing namespace.
     */
    abstract public function testingNamespace(): string;

    /**
     * Database factory namespace.
     */
    abstract public function factoryNamespace(): string;

    /**
     * Database seeder namespace.
     */
    abstract public function seederNamespace(): string;

    /**
     * Preset has custom stub path.
     */
    abstract public function hasCustomStubPath(): bool;
}
