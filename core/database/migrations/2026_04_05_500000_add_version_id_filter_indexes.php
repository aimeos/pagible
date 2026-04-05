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

        // Standalone lang index: enables filter-first plans for lang queries
        // (existing indexes (published, lang) and (id, lang) can't be used for lang-first scans)
        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->index('lang', 'cms_versions_lang_index');
        });

        // (editor, id) composite: enables filter-first plans
        // For MySQL, standalone editor index already exists but explicit composite helps PostgreSQL/SQL Server
        Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
            $table->index(['editor', 'id'], 'cms_versions_editor_id_index');
        });

        if ($driver === 'sqlsrv') {
            // SQL Server computed columns don't include PK implicitly, need explicit composites
            $db->statement('CREATE INDEX cms_versions_data_theme_id_index ON cms_versions (data_theme, id)');
            $db->statement('CREATE INDEX cms_versions_data_status_id_index ON cms_versions (data_status, id)');
            $db->statement('CREATE INDEX cms_versions_data_cache_id_index ON cms_versions (data_cache, id)');
            $db->statement('CREATE INDEX cms_versions_data_type_id_index ON cms_versions (data_type, id)');
        }
        // MySQL/MariaDB: standalone virtual column indexes already provide (data_*, id) order in InnoDB
        // PostgreSQL: (expression, id) indexes already created in migration 400000
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
            $table->dropIndex('cms_versions_lang_index');
            $table->dropIndex('cms_versions_editor_id_index');
        });

        if ($driver === 'sqlsrv') {
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_id_index ON cms_versions');
        }
    }
};
