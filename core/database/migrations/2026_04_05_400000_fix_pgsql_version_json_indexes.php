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

        // Composite indexes: id (for JOIN) + expression (for filter)
        $db->statement("CREATE INDEX cms_versions_id_data_theme_idx ON cms_versions (id, (data->>'theme'))");
        $db->statement("CREATE INDEX cms_versions_id_data_status_idx ON cms_versions (id, (data->>'status'))");
        $db->statement("CREATE INDEX cms_versions_id_data_cache_idx ON cms_versions (id, (data->>'cache'))");
        $db->statement("CREATE INDEX cms_versions_id_data_type_idx ON cms_versions (id, (data->>'type'))");
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
        $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_theme_idx');
        $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_status_idx');
        $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_cache_idx');
        $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_type_idx');

        // Restore original single-column expression indexes
        $db->statement("CREATE INDEX cms_versions_data_theme_index ON cms_versions ((data->>'theme'))");
        $db->statement("CREATE INDEX cms_versions_data_type_index ON cms_versions ((data->>'type'))");
        $db->statement("CREATE INDEX cms_versions_data_status_index ON cms_versions (((data->>'status')::smallint))");
        $db->statement("CREATE INDEX cms_versions_data_cache_index ON cms_versions (((data->>'cache')::smallint))");
    }
};
