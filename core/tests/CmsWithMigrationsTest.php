<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class CmsWithMigrationsTest extends CmsTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    public function testFailedQueryDoesNotTriggerMigrationRollback()
    {
        $this->expectException( QueryException::class );

        DB::table( 'cms_missing_table' )->get();
    }
}
