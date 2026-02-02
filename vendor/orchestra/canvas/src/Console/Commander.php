<?php

namespace Orchestra\Canvas\Console;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\Collection;
use Orchestra\Canvas\CanvasServiceProvider;
use Orchestra\Canvas\Core\Concerns\CreatesUsingGeneratorPreset;
use Orchestra\Canvas\LaravelServiceProvider;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * @codeCoverageIgnore
 */
class Commander extends \Orchestra\Testbench\Console\Commander
{
    /** {@inheritDoc} */
    protected string $environmentFile = '.env';

    /** {@inheritDoc} */
    protected array $providers = [];

    /** {@inheritDoc} */
    #[\Override]
    protected function resolveApplicationCallback()
    {
        return static function ($app) {
            $app->register(CanvasServiceProvider::class);
        };
    }

    /** {@inheritDoc} */
    #[\Override]
    public function laravel()
    {
        if (! $this->app instanceof LaravelApplication) {
            $app = parent::laravel();

            /** @var \Illuminate\Contracts\Console\Kernel $kernel */
            $kernel = $app->make(ConsoleKernel::class);

            $app->register(LaravelServiceProvider::class);

            Collection::make($kernel->all())
                ->reject(
                    static fn (SymfonyCommand $command, string $name) => \in_array(CreatesUsingGeneratorPreset::class, class_uses_recursive($command))
                )->each(static function (SymfonyCommand $command) {
                    $command->setHidden(true);
                });
        }

        /** @phpstan-ignore return.type */
        return $this->app;
    }
}
