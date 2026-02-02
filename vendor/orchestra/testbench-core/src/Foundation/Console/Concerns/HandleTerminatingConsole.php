<?php

namespace Orchestra\Testbench\Foundation\Console\Concerns;

use Orchestra\Testbench\Foundation\Console\TerminatingConsole;

/**
 * @deprecated
 *
 * @codeCoverageIgnore
 */
trait HandleTerminatingConsole
{
    /**
     * Register a callback to be run before terminating the command.
     *
     * @param  callable():void  $callback
     * @return void
     *
     * @deprecated
     *
     * @codeCoverageIgnore
     */
    #[\Deprecated('Use `Orchestra\Testbench\Foundation\Console\TerminatingConsole::before()` instead.', since: '9.7.0')]
    protected function beforeTerminating(callable $callback): void
    {
        TerminatingConsole::before($callback);
    }

    /**
     * Handle terminating console.
     *
     * @return void
     *
     * @deprecated Use `Orchestra\Testbench\Foundation\Console\TerminatingConsole::handle()` instead.
     *
     * @codeCoverageIgnore
     */
    #[\Deprecated('Use `Orchestra\Testbench\Foundation\Console\TerminatingConsole::handle()` instead.', since: '9.7.0')]
    protected function handleTerminatingConsole(): void
    {
        TerminatingConsole::handle();
    }
}
