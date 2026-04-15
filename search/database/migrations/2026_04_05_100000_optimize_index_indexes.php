<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);
        $db = $schema->getConnection();
        $driver = $db->getDriverName();

        if( $driver === 'sqlite' ) {
            return;
        }

        $columns = collect($schema->getColumns('cms_index'))->pluck('name')->all();
        $indexes = collect($schema->getIndexes('cms_index'))->pluck('name')->all();

        if( $driver === 'pgsql' )
        {
            if( !in_array('content_vector', $columns) ) {
                $db->statement("ALTER TABLE cms_index ADD COLUMN content_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', coalesce(content, ''))) STORED");
            }

            if( !in_array('cms_index_content_vector_gin', $indexes) ) {
                $db->statement('CREATE INDEX cms_index_content_vector_gin ON cms_index USING gin(content_vector)');
            }
        }

        if( in_array('cms_index_tenant_id_indexable_type_latest_index', $indexes) )
        {
            $schema->table('cms_index', function($table) {
                $table->dropIndex(['tenant_id', 'indexable_type', 'latest']);
            });
        }

        if( in_array('cms_index_indexable_id_indexable_type_latest_tenant_id_index', $indexes) )
        {
            $schema->table('cms_index', function($table) {
                $table->dropIndex(['indexable_id', 'indexable_type', 'latest', 'tenant_id']);
            });
        }

        if( !in_array('cms_index_tenant_id_indexable_type_latest_indexable_id_index', $indexes) )
        {
            $schema->table('cms_index', function($table) {
                $table->index(['tenant_id', 'indexable_type', 'latest', 'indexable_id']);
            });
        }
    }
};
