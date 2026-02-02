<?php

namespace Orchestra\Canvas\Console;

use Orchestra\Canvas\Core\Concerns\CodeGenerator;
use Orchestra\Canvas\Core\Concerns\TestGenerator;
use Orchestra\Canvas\Core\Concerns\UsesGeneratorOverrides;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Foundation/Console/MailMakeCommand.php
 */
#[AsCommand(name: 'make:mail', description: 'Create a new email class')]
class MailMakeCommand extends \Illuminate\Foundation\Console\MailMakeCommand
{
    use CodeGenerator;
    use TestGenerator;
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
     * Run after code successfully generated.
     */
    public function afterCodeHasBeenGenerated(string $className, string $path): void
    {
        if ($this->option('markdown') !== false) {
            $this->writeMarkdownTemplate();
        }

        if ($this->option('view') !== false) {
            $this->writeView();
        }
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
}
