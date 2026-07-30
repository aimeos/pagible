<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;


trait CmsWithMigrations
{
    protected function defineDatabaseMigrations()
    {
        \Orchestra\Testbench\after_resolving( $this->app, 'migrator', static function( $migrator ) {
            $migrator->path( \Orchestra\Testbench\default_migration_path() );
            $migrator->path( __DIR__ . '/database/migrations' );
            $migrator->path( dirname( __DIR__ ) . '/database/migrations' );
            $migrator->path(
                dirname( ( new \ReflectionClass( \Aimeos\Cms\CashierAccess::class ) )->getFileName(), 2 )
                    . '/database/migrations'
            );
        } );
    }
}
