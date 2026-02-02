<?php

namespace Orchestra\Testbench\Console;

use Orchestra\Testbench\Foundation\Console\Kernel as ConsoleKernel;
use Throwable;

/**
 * @codeCoverageIgnore
 */
final class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Report the exception to the exception handler.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    #[\Override]
    protected function reportException(Throwable $e)
    {
        throw $e;
    }

    /**
     * Determine if the kernel should discover commands.
     *
     * @return bool
     */
    #[\Override]
    protected function shouldDiscoverCommands()
    {
        return \get_class($this) === __CLASS__;
    }
}
