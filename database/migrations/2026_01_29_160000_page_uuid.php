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

        $this->copyPagesTable();


        // Add UUID columns

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

        DB::table('cms_page_element')->update([
            'page_uuid' => DB::raw('(SELECT id FROM cms_pages_new WHERE cms_pages_new.oid = cms_page_element.page_id)')
        ]);

        DB::table('cms_page_file')->update([
            'page_uuid' => DB::raw('(SELECT id FROM cms_pages_new WHERE cms_pages_new.oid = cms_page_file.page_id)')
        ]);

        DB::table('cms_page_search')->update([
            'page_uuid' => DB::raw('(SELECT id FROM cms_pages_new WHERE cms_pages_new.oid = cms_page_search.page_id)')
        ]);

        DB::table('cms_versions')->whereIn('versionable_id', function ($query) {
            $query->select('id')->from('cms_pages_new');
        })->update([
            'versionable_id' => DB::raw('(SELECT id FROM cms_pages_new WHERE cms_pages_new.oid = cms_versions.versionable_id)')
        ]);


        // Remove old primary / foreign keys

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropIndex('cms_page_element_page_id_element_id_unique'); // for SQLite
            $table->dropColumn('page_id');
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropIndex('cms_page_file_page_id_file_id_unique'); // for SQLite
            $table->dropColumn('page_id');
        });

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->dropForeign(['page_id']);
            $table->dropColumn('page_id');
        });


        // Delete old pages table and rename new one

        $schema->dropIfExists('cms_pages');
        $schema->dropColumns('cms_pages_new', 'oid');
        $schema->rename('cms_pages_new', 'cms_pages');


        // Promote UUIDs to primary / foreign keys

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->renameColumn('page_uuid', 'page_id');
        });

        $schema->table('cms_page_element', function (Blueprint $table) {
            $table->foreign('page_id')->references('id')->on('cms_pages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['page_id', 'element_id']);
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->renameColumn('page_uuid', 'page_id');
        });

        $schema->table('cms_page_file', function (Blueprint $table) {
            $table->foreign('page_id')->references('id')->on('cms_pages')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['page_id', 'file_id']);
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


    protected function copyPagesTable()
    {
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_pages_new', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('oid');
            $table->string('tenant_id', 250);
            $table->string('name');
            $table->string('path');
            $table->string('to');
            $table->string('title');
            $table->string('domain');
            $table->string('lang', 5);
            $table->string('tag', 30);
            $table->string('type', 30);
            $table->string('theme', 30);
            $table->smallInteger('cache');
            $table->smallInteger('status');
            $table->integer('related_id')->nullable();
            $table->json('meta');
            $table->json('config');
            $table->json('content');
            $table->string('editor');
            $table->softDeletes();
            $table->timestamps();
            $table->nestedSet('id', 'uuid');

            $table->unique(['path', 'domain', 'tenant_id']);
            $table->index(['_lft', '_rgt', 'tenant_id', 'status']);
            $table->index(['tag', 'lang', 'tenant_id', 'status']);
            $table->index(['lang', 'tenant_id', 'status']);
            $table->index(['related_id', 'tenant_id']);
            $table->index(['parent_id', 'tenant_id']);
            $table->index(['domain', 'tenant_id']);
            $table->index(['to', 'tenant_id']);
            $table->index(['name', 'tenant_id']);
            $table->index(['title', 'tenant_id']);
            $table->index(['type', 'tenant_id']);
            $table->index(['theme', 'tenant_id']);
            $table->index(['cache', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
            $table->index(['deleted_at']);
        });

        DB::table('cms_pages_new')->insert(
            DB::table('cms_pages')->get()->map(function ($row) {
                $row->oid = $row->id;
                $row->id = Str::uuid()->toString();
                return (array) $row;
            })->toArray()
        );

        DB::table('cms_pages_new')->update([
            'parent_id' => DB::raw('(SELECT id FROM cms_pages_new WHERE cms_pages_new.oid = cms_pages_new.parent_id)')
        ]);
    }
};
