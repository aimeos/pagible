<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection(config('cms.db', 'sqlite'));

        if ($db->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop mismatched status/cache indexes (had ::smallint cast that doesn't match Laravel's text queries)
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_index');
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_index');

        // Drop single-column expression indexes (replaced by composites with id for join support)
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_index');
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_index');

        // Composite indexes: expression (for filter-first) + id (for join)
        // (expression, id) supports both point lookups AND range scans by expression
        $db->statement("CREATE INDEX cms_versions_data_theme_id_index ON cms_versions ((data->>'theme'), id)");
        $db->statement("CREATE INDEX cms_versions_data_status_id_index ON cms_versions ((data->>'status'), id)");
        $db->statement("CREATE INDEX cms_versions_data_cache_id_index ON cms_versions ((data->>'cache'), id)");
        $db->statement("CREATE INDEX cms_versions_data_type_id_index ON cms_versions ((data->>'type'), id)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $db = DB::connection(config('cms.db', 'sqlite'));

        if ($db->getDriverName() !== 'pgsql') {
            return;
        }

        // Drop composite indexes
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_id_index');
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_id_index');
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_id_index');
        $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_id_index');

        // Restore original single-column expression indexes
        $db->statement("CREATE INDEX cms_versions_data_theme_index ON cms_versions ((data->>'theme'))");
        $db->statement("CREATE INDEX cms_versions_data_type_index ON cms_versions ((data->>'type'))");
        $db->statement("CREATE INDEX cms_versions_data_status_index ON cms_versions (((data->>'status')::smallint))");
        $db->statement("CREATE INDEX cms_versions_data_cache_index ON cms_versions (((data->>'cache')::smallint))");
    }
};
