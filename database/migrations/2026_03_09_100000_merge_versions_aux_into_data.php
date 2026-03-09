<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        $name = config('cms.db', 'sqlite');
        $db = DB::connection($name);

        if( !Schema::connection($name)->hasColumn('cms_versions', 'aux') ) {
            return;
        }

        $db->table('cms_versions')->orderBy('id')->chunk(100, function ($versions) use ($db) {
            foreach ($versions as $version) {
                $data = json_decode($version->data, true) ?: [];
                $aux = json_decode($version->aux, true) ?: [];

                $db->table('cms_versions')
                    ->where('id', $version->id)
                    ->update(['data' => json_encode(array_merge($data, $aux))]);
            }
        });

        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->dropColumn('aux');
        });
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
