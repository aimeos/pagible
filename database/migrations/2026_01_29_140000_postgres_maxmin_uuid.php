<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        DB::connection($name)->statement("
            CREATE OR REPLACE FUNCTION uuid_max(uuid, uuid)
            RETURNS uuid AS $$
            BEGIN
                RETURN GREATEST($1, $2);
            END;
            $$ LANGUAGE plpgsql IMMUTABLE STRICT;
        ");

        DB::connection($name)->statement("
            CREATE OR REPLACE FUNCTION uuid_min(uuid, uuid)
            RETURNS uuid AS $$
            BEGIN
                RETURN LEAST($1, $2);
            END;
            $$ LANGUAGE plpgsql IMMUTABLE STRICT;
        ");

        DB::connection($name)->statement("
            CREATE OR REPLACE AGGREGATE max(uuid) (
                SFUNC = uuid_max,
                STYPE = uuid,
                COMBINEFUNC = uuid_max,
                PARALLEL = SAFE,
                SORTOP = operator (>) -- Essential for index optimization
            );
        ");

        DB::connection($name)->statement("
            CREATE OR REPLACE AGGREGATE min(uuid) (
                SFUNC = uuid_min,
                STYPE = uuid,
                COMBINEFUNC = uuid_min,
                PARALLEL = SAFE,
                SORTOP = operator (<) -- Essential for index optimization
            );
        ");
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if( DB::getDriverName() !== 'pgsql' ) {
            return;
        }

        $name = config('cms.db', 'sqlite');

        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS max(uuid);");
        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS min(uuid);");
        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS uuid_max(uuid, uuid);");
        DB::connection($name)->statement("DROP AGGREGATE IF EXISTS uuid_min(uuid, uuid);");
    }
};
