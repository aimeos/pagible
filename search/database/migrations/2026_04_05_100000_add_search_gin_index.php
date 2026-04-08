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

        $indexes = collect($schema->getIndexes('cms_index'))->pluck('name')->all();

        if( $driver === 'pgsql' && !in_array('cms_index_content_gin', $indexes) ) {
            $db->statement("CREATE INDEX cms_index_content_gin ON cms_index USING gin(to_tsvector('simple', coalesce(content, '')))");
        }

        if( !in_array('cms_index_tenant_id_indexable_type_latest_index', $indexes) ) {
            $schema->table('cms_index', function($table) {
                $table->index(['tenant_id', 'indexable_type', 'latest']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);
        $db = $schema->getConnection();
        $driver = $db->getDriverName();

        if( $driver === 'sqlite' ) {
            return;
        }

        $indexes = collect($schema->getIndexes('cms_index'))->pluck('name')->all();

        if( in_array('cms_index_content_gin', $indexes) ) {
            $db->statement('DROP INDEX cms_index_content_gin');
        }

        if( in_array('cms_index_tenant_id_indexable_type_latest_index', $indexes) ) {
            $schema->table('cms_index', function($table) {
                $table->dropIndex(['tenant_id', 'indexable_type', 'latest']);
            });
        }
    }
};
