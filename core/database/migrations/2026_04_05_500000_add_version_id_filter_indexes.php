<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $db = DB::connection($name);
        $driver = $db->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // id + editor: all databases
        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->index(['id', 'editor'], 'cms_versions_id_editor_idx');
        });

        if (in_array($driver, ['mysql', 'mariadb'])) {
            // MySQL virtual columns: composite with id for join-friendly access
            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->index(['id', 'data_theme'], 'cms_versions_id_data_theme_idx');
                $table->index(['id', 'data_status'], 'cms_versions_id_data_status_idx');
                $table->index(['id', 'data_cache'], 'cms_versions_id_data_cache_idx');
                $table->index(['id', 'data_type'], 'cms_versions_id_data_type_idx');
            });
        } elseif ($driver === 'sqlsrv') {
            // SQL Server computed columns
            $db->statement('CREATE INDEX cms_versions_id_data_theme_idx ON cms_versions (id, data_theme)');
            $db->statement('CREATE INDEX cms_versions_id_data_status_idx ON cms_versions (id, data_status)');
            $db->statement('CREATE INDEX cms_versions_id_data_cache_idx ON cms_versions (id, data_cache)');
            $db->statement('CREATE INDEX cms_versions_id_data_type_idx ON cms_versions (id, data_type)');
        }
        // PostgreSQL: (id, expression) indexes already created in migration 400000
        // Only (id, editor) is new for PostgreSQL, handled above
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');
        $db = DB::connection($name);
        $driver = $db->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->dropIndex('cms_versions_id_editor_idx');
        });

        if (in_array($driver, ['mysql', 'mariadb', 'sqlsrv'])) {
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_theme_idx ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_status_idx ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_cache_idx ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_data_type_idx ON cms_versions');
        }
    }
};
