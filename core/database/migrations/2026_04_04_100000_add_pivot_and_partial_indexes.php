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

        // Add reverse indexes on pivot tables (MySQL creates these implicitly for FKs)
        if( !$this->hasIndex($schema, 'cms_page_file', 'cms_page_file_file_id_index') ) {
            $schema->table('cms_page_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        if( !$this->hasIndex($schema, 'cms_page_element', 'cms_page_element_element_id_index') ) {
            $schema->table('cms_page_element', function (Blueprint $table) {
                $table->index('element_id');
            });
        }

        if( !$this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        if( !$this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->index('element_id');
            });
        }

        if( !$this->hasIndex($schema, 'cms_element_file', 'cms_element_file_file_id_index') ) {
            $schema->table('cms_element_file', function (Blueprint $table) {
                $table->index('file_id');
            });
        }

        // Add composite indexes for tenant + soft-delete filtering
        if( !$this->hasIndex($schema, 'cms_files', 'cms_files_tenant_id_deleted_at_index') ) {
            $schema->table('cms_files', function (Blueprint $table) {
                $table->index(['tenant_id', 'deleted_at']);
            });
        }

        if( !$this->hasIndex($schema, 'cms_elements', 'cms_elements_tenant_id_deleted_at_index') ) {
            $schema->table('cms_elements', function (Blueprint $table) {
                $table->index(['tenant_id', 'deleted_at']);
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

        if( $this->hasIndex($schema, 'cms_elements', 'cms_elements_tenant_id_deleted_at_index') ) {
            $schema->table('cms_elements', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'deleted_at']);
            });
        }

        if( $this->hasIndex($schema, 'cms_files', 'cms_files_tenant_id_deleted_at_index') ) {
            $schema->table('cms_files', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'deleted_at']);
            });
        }

        if( $this->hasIndex($schema, 'cms_element_file', 'cms_element_file_file_id_index') ) {
            $schema->table('cms_element_file', function (Blueprint $table) {
                $table->dropIndex(['file_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_version_element', 'cms_version_element_element_id_index') ) {
            $schema->table('cms_version_element', function (Blueprint $table) {
                $table->dropIndex(['element_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_version_file', 'cms_version_file_file_id_index') ) {
            $schema->table('cms_version_file', function (Blueprint $table) {
                $table->dropIndex(['file_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_page_element', 'cms_page_element_element_id_index') ) {
            $schema->table('cms_page_element', function (Blueprint $table) {
                $table->dropIndex(['element_id']);
            });
        }

        if( $this->hasIndex($schema, 'cms_page_file', 'cms_page_file_file_id_index') ) {
            $schema->table('cms_page_file', function (Blueprint $table) {
                $table->dropIndex(['file_id']);
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
