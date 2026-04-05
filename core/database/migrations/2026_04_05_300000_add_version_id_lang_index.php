<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $name = config('cms.db', 'sqlite');
        $indexes = collect(Schema::connection($name)->getIndexes('cms_versions'))->pluck('name')->all();

        if( in_array('cms_versions_id_lang_index', $indexes) ) {
            return;
        }

        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->index(['id', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');
        $indexes = collect(Schema::connection($name)->getIndexes('cms_versions'))->pluck('name')->all();

        if( in_array('cms_versions_id_lang_index', $indexes) ) {
            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->dropIndex(['id', 'lang']);
            });
        }
    }
};
