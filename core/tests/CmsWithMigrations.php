<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


trait CmsWithMigrations
{
    protected function defineDatabaseMigrations()
    {
        // Register the Laravel default migrations for the next migrate:fresh call.
        // Testbench's WithLaravelMigrations can run rollback hooks inside the
        // RefreshDatabase transaction on later tests. PostgreSQL rejects those
        // migration queries if the transaction is already marked as failed.
        \Orchestra\Testbench\after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(\Orchestra\Testbench\default_migration_path());
        });
    }
}
