<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class CmsWithMigrationsTest extends CmsTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    public function testFailedQueryDoesNotTriggerMigrationRollback()
    {
        $this->expectException( QueryException::class );

        DB::table( 'cms_missing_table' )->get();
    }


    public function testGeneratedVersionColumnsExist()
    {
        $driver = \Aimeos\Cms\DB::connection()->getDriverName();

        if( !in_array( $driver, ['mysql', 'mariadb', 'sqlsrv'], true ) ) {
            $this->markTestSkipped( 'Generated columns are only used for MySQL, MariaDB and SQL Server' );
        }

        // DB::qualify() uses the generated columns which are created by the migrations
        $fields = ( new \ReflectionClassConstant( \Aimeos\Cms\DB::class, 'GENERATED_COLUMNS' ) )->getValue();
        $columns = Schema::connection( \Aimeos\Cms\DB::name() )->getColumnListing( 'cms_versions' );

        foreach( $fields as $field ) {
            $this->assertContains( 'data_' . $field, $columns );
        }
    }
}
