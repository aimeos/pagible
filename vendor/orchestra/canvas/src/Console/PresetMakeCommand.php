<?php

namespace Orchestra\Canvas\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Orchestra\Canvas\Core\Commands\GeneratorCommand;
use Orchestra\Canvas\Core\Concerns\ResolvesPresetStubs;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;
use function Orchestra\Sidekick\join_paths;
use function Orchestra\Testbench\package_path;

/**
 * @codeCoverageIgnore
 */
#[AsCommand(name: 'preset', description: 'Create canvas.yaml for the project')]
class PresetMakeCommand extends GeneratorCommand
{
    use ResolvesPresetStubs;

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Preset';

    /**
     * Interact with the user before validating the input.
     *
     * @return void
     */
    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        if (\is_null($input->getArgument('name'))) {
            $input->setArgument('name', select(
                label: 'The preset type?',
                options: [
                    'laravel' => 'Application',
                    'package' => 'Package',
                ],
                required: true,
            ));
        }

        if (\is_null($input->getOption('namespace'))) {
            $files = new Filesystem;
            $composer = $files->json(package_path('composer.json'));

            $namespaces = Collection::make(Arr::wrap(data_get($composer, 'autoload.psr-4')))
                ->keys()
                ->transform(static fn ($namespace) => rtrim($namespace, '\\'))
                ->mapWithKeys(static fn ($namespace) => [$namespace => $namespace]);

            if ($namespaces->isNotEmpty()) {
                if ($namespaces->count() === 1) {
                    $input->setOption('namespace', $namespaces->first());
                } else {
                    $input->setOption('namespace', select(
                        label: 'The root namespace for your package?',
                        options: $namespaces->all(),
                        required: true,
                    ));
                }
            }
        }

        parent::interact($input, $output);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    #[\Override]
    protected function getStub()
    {
        $name = Str::lower($this->getNameInput());

        $stub = join_paths(__DIR__, 'stubs', 'preset');

        return $this->files->exists("{$stub}.{$name}.stub")
            ? "{$stub}.{$name}.stub"
            : "{$stub}.laravel.stub";
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
        return join_paths($this->generatorPreset()->basePath(), 'canvas.yaml');
    }

    /**
     * Get the root namespace for the class.
     */
    #[\Override]
    protected function rootNamespace(): string
    {
        /** @var string $namespace */
        $namespace = transform(
            $this->option('namespace'), static fn (string $namespace) => trim($namespace) /** @phpstan-ignore argument.type */
        );

        if (! empty($namespace)) {
            return $namespace;
        }

        return match ($this->argument('name')) {
            'package' => 'PackageName',
            /* 'laravel' */
            default => rtrim($this->laravel->getNamespace(), '\\'),
        };
    }

    /**
     * Get the console command options.
     *
     * @return array<int, array>
     */
    #[\Override]
    protected function getOptions()
    {
        return [
            ['namespace', null, InputOption::VALUE_OPTIONAL, 'Root namespace'],
        ];
    }
}
