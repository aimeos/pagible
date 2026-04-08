<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if( DB::getDriverName() !== 'pgsql' ) {
            return;
        }

        $name = config('cms.db', 'sqlite');

        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS max(uuid);");
        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS min(uuid);");
        DB::connection($name)->statement("DROP FUNCTION IF EXISTS uuid_max(uuid, uuid);");
        DB::connection($name)->statement("DROP FUNCTION IF EXISTS uuid_min(uuid, uuid);");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
