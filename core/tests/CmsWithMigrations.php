<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;


trait CmsWithMigrations
{
    private static bool $cmsPrepared = false;


    protected function defineDatabaseMigrations()
    {
        // Persistent databases share Laravel's migration state between classes.
        // Reset it once so each class gets its configured seeded or empty baseline.
        if( !self::$cmsPrepared ) {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            self::$cmsPrepared = true;
        }

        // Register the Laravel migrations for migrate:fresh without Testbench's
        // per-test migrate:rollback hook, which runs inside RefreshDatabase's
        // transaction and fails after PostgreSQL has marked it as aborted.
        \Orchestra\Testbench\after_resolving($this->app, 'migrator', static function ($migrator) {
            $migrator->path(\Orchestra\Testbench\default_migration_path());
        });
    }
}
