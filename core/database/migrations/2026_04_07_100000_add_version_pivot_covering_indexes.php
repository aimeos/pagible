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
     *
     * @return void
     */
    public function up()
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);

        // Covering composite indexes so correlated count/exists subqueries driven
        // by file_id / element_id can seek the pivot directly without a rowid
        // lookup for version_id. Replaces the single-column indexes added in
        // 2026_04_04_100000_add_pivot_and_partial_indexes.php.

        if( !$this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_version_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->index(['file_id', 'version_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->dropIndex(['file_id']);
            });
        }

        if( !$this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_version_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->index(['element_id', 'version_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->dropIndex(['element_id']);
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
        $schema = Schema::connection($name);

        if( !$this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->index('element_id');
            });
        }

        if( $this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_version_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->dropIndex(['element_id', 'version_id']);
            });
        }

        if( !$this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        if( $this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_version_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->dropIndex(['file_id', 'version_id']);
            });
        }
    }


    /**
     * Checks if the given index exists in the table.
     *
     * @param \Illuminate\Database\Schema\Builder $schema Schema builder
     * @param string $table Table name
     * @param string $indexName Index name
     * @return bool
     */
    private function hasIndex($schema, string $table, string $indexName) : bool
    {
        return in_array($indexName, collect($schema->getIndexes($table))->pluck('name')->all());
    }
};
