<?php

namespace Orchestra\Canvas\Console;

use Orchestra\Canvas\Core\Concerns\CodeGenerator;
use Orchestra\Canvas\Core\Concerns\UsesGeneratorOverrides;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Foundation/Console/PolicyMakeCommand.php
 */
#[AsCommand(name: 'make:policy', description: 'Create a new policy class')]
class PolicyMakeCommand extends \Illuminate\Foundation\Console\PolicyMakeCommand
{
    use CodeGenerator;
    use UsesGeneratorOverrides;

    /**
     * Configures the current command.
     *
     * @return void
     */
    #[\Override]
    protected function configure()
    {
        parent::configure();

        $this->addGeneratorPresetOptions();
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    #[\Override]
    public function handle()
    {
        /** @phpstan-ignore return.type */
        return $this->generateCode() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Qualify the given model class base name.
     *
     * @return string
     */
    #[\Override]
    protected function qualifyModel(string $model)
    {
        return $this->qualifyModelUsingCanvas($model);
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    #[\Override]
    protected function getPath($name)
    {
        return $this->getPathUsingCanvas($name);
    }

    /**
     * Get the root namespace for the class.
     *
     * @return string
     */
    #[\Override]
    protected function rootNamespace()
    {
        return $this->rootNamespaceUsingCanvas();
    }

    /**
     * Get the model for the default guard's user provider.
     *
     * @return string|null
     */
    #[\Override]
    protected function userProviderModel()
    {
        /** @var string|null $guard */
        $guard = $this->option('guard');

        return $this->userProviderModelUsingCanvas($guard);
    }

    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     */
    #[\Override]
    protected function findAvailableModels()
    {
        return $this->findAvailableModelsUsingCanvas();
    }
}
