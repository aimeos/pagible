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
        $schema = Schema::connection(config('cms.db', 'sqlite'));

        if( in_array( $schema->getColumnType('cms_pages', 'id'), ['varchar', 'char', 'guid', 'uuid'] ) ) {
            return;
        }

        // Add UUID columns

        $schema->table('cms_pages', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->uuid('page_uuid')->nullable()->after('page_id');
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->uuid('page_uuid')->nullable()->after('page_id');
        });

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->uuid('page_uuid')->nullable()->after('page_id');
        });

        // Add UUID values

        DB::table('cms_pages')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($pages) {
                foreach ($pages as $page) {
                    DB::table('cms_pages')
                        ->where('id', $page->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        DB::table('cms_page_element')
            ->join('cms_pages', 'cms_page_element.page_id', '=', 'cms_pages.id')
            ->update(['cms_page_element.page_uuid' => DB::raw('cms_pages.uuid')]);

        DB::table('cms_page_file')
            ->join('cms_pages', 'cms_page_file.page_id', '=', 'cms_pages.id')
            ->update(['cms_page_file.page_uuid' => DB::raw('cms_pages.uuid')]);

        DB::table('cms_page_search')
            ->join('cms_pages', 'cms_page_search.page_id', '=', 'cms_pages.id')
            ->update(['cms_page_search.page_uuid' => DB::raw('cms_pages.uuid')]);

        DB::table('cms_versions')
            ->join('cms_pages', 'cms_versions.versionable_id', '=', 'cms_pages.id')
            ->update(['cms_versions.versionable_id' => DB::raw('cms_pages.uuid')]);


        // Remove old primary / foreign keys

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });

        $schema->table('cms_pages', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // Promote UUIDs to primary / foreign keys

        $schema->table('cms_pages', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
            $table->uuid('id')->primary()->change();
        });

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->renameColumn('page_uuid', 'page_id');
        });

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->foreign('page_id')->references('id')->on('cms_pages')->cascadeOnDelete()->cascadeOnUpdate();
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->renameColumn('page_uuid', 'page_id');
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->foreign('page_id')->references('id')->on('cms_pages')->cascadeOnDelete()->cascadeOnUpdate();
        });

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->renameColumn('page_uuid', 'page_id');
        });

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->foreign('page_id')->references('id')->on('cms_pages')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // removed by previous migration
    }
};
