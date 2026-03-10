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

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) {
            $table->uuid('latest_id')->nullable();
        });

        Schema::connection($name)->table('cms_elements', function (Blueprint $table) {
            $table->uuid('latest_id')->nullable();
        });

        Schema::connection($name)->table('cms_files', function (Blueprint $table) {
            $table->uuid('latest_id')->nullable();
        });

        $db = DB::connection($name);
        $map = [
            'cms_pages' => 'Aimeos\\Cms\\Models\\Page',
            'cms_elements' => 'Aimeos\\Cms\\Models\\Element',
            'cms_files' => 'Aimeos\\Cms\\Models\\File'
        ];

        foreach ($map as $table => $type) {
            $db->table($table)->orderBy('id')->chunk(100, function ($items) use ($db, $table, $type) {
                foreach ($items as $item) {
                    $version = $db->table('cms_versions')
                        ->where('versionable_id', $item->id)
                        ->where('versionable_type', $type)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($version) {
                        $db->table($table)->where('id', $item->id)->update([
                            'latest_id' => $version->id,
                        ]);
                    }
                }
            });
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $name = config('cms.db', 'sqlite');

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) {
            $table->dropColumn('latest_id');
        });

        Schema::connection($name)->table('cms_elements', function (Blueprint $table) {
            $table->dropColumn('latest_id');
        });

        Schema::connection($name)->table('cms_files', function (Blueprint $table) {
            $table->dropColumn('latest_id');
        });
    }
};
