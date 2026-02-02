<?php

namespace Orchestra\Testbench\Foundation\Process;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\ProcessUtils;
use Laravel\SerializableClosure\SerializableClosure;
use Symfony\Component\Process\Process;

use function Orchestra\Sidekick\laravel_version_compare;
use function Orchestra\Testbench\defined_environment_variables;
use function Orchestra\Testbench\php_binary;

/**
 * @internal
 */
final class RemoteCommand
{
    /**
     * Construct a new action.
     *
     * @param  string  $workingPath
     * @param  array<string, mixed>|string  $env
     * @param  bool|null  $tty
     */
    public function __construct(
        public string $workingPath,
        public array|string $env = [],
        public ?bool $tty = null,
    ) {}

    /**
     * Execute the command.
     *
     * @param  (\Closure():(mixed))|array<int, string>|string  $command
     * @return \Orchestra\Testbench\Foundation\Process\ProcessDecorator
     */
    public function handle(string $commander, Closure|array|string $command): ProcessDecorator
    {
        $env = \is_string($this->env) ? ['APP_ENV' => $this->env] : $this->env;

        Arr::add($env, 'TESTBENCH_PACKAGE_REMOTE', '(true)');

        if ($command instanceof Closure) {
            $env['LARAVEL_INVOKABLE_CLOSURE'] = transform(serialize(new SerializableClosure($command)), function ($invokableClosure) {
                return laravel_version_compare('12.26.0', '<')
                    ? $invokableClosure
                    : base64_encode($invokableClosure);
            });

            $env['APP_KEY'] = $env['APP_KEY'] ?? config('app.key') ?? false;
            $commands = ['invoke-serialized-closure'];
        } else {
            $commands = Arr::wrap($command);
        }

        $process = Process::fromShellCommandline(
            command: Arr::join([php_binary(true), ProcessUtils::escapeArgument($commander), ...$commands], ' '),
            cwd: $this->workingPath,
            env: array_merge(defined_environment_variables(), $env)
        );

        if (\is_bool($this->tty)) {
            $process->setTty($this->tty);
        }

        return new ProcessDecorator($process, $command);
    }
}
