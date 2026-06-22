<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Orchestra\Testbench\Concerns\WithLaravelMigrations;


trait CmsWithMigrations
{
    use WithLaravelMigrations;


    protected function defineDatabaseMigrations()
    {
        \Orchestra\Testbench\after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(\Orchestra\Testbench\default_migration_path());
        });
    }
}
