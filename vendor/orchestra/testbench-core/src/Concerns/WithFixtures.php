<?php

namespace Orchestra\Testbench\Concerns;

use Illuminate\Support\Str;
use Pest\Support\Backtrace;

use function Orchestra\Sidekick\Filesystem\filename_from_classname;

/**
 * @api
 *
 * @codeCoverageIgnore
 */
trait WithFixtures
{
    use InteractsWithPest;

    /**
     * Setup test case to include fixture file using ".fixtures.php" suffix if it's available.
     *
     * @return void
     */
    protected static function setupWithFixturesForTestingEnvironment(): void
    {
        $classFileName = static::isRunningViaPestPrinter(static::class)
            ? Backtrace::testFile()
            : filename_from_classname(static::class);

        if ($classFileName === false) {
            return;
        }

        if (! is_file($fixtureFileName = Str::replaceLast('.php', '.fixtures.php', $classFileName))) {
            return;
        }

        require_once $fixtureFileName;
    }
}
