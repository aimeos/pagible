<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\Artisan;


class InstallTest extends \Orchestra\Testbench\TestCase
{
    public function testFailure(): void
    {
        $this->commands($calls, 1);

        $this->artisan('cms:install')->assertExitCode(1);
    }


    public function testSeed(): void
    {
        $this->commands($calls);

        $this->artisan('cms:install', ['--seed' => true])->assertExitCode(0);

        $this->assertEquals(['cms:install:core' => true, 'cms:install:graphql' => false, 'cms:install:ai' => false], $calls);
    }


    protected function getPackageProviders($app): array
    {
        return [\Aimeos\Cms\ServiceProvider::class];
    }


    private function commands(?array &$calls, int $code = 0): void
    {
        $calls = [];

        Artisan::command('cms:install:core {--seed}', function () use (&$calls) {
            $calls['cms:install:core'] = $this->option('seed');
        });

        Artisan::command('cms:install:graphql', function () use (&$calls) {
            $calls['cms:install:graphql'] = false;
        });

        Artisan::command('cms:install:ai', function () use (&$calls, $code) {
            $calls['cms:install:ai'] = false;

            return $code;
        });
    }
}
