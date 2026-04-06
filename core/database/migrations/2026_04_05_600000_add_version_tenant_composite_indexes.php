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

        // Skip if base migration already created composite indexes (fresh install)
        $indexes = collect(Schema::connection($name)->getIndexes('cms_versions'))->pluck('name')->all();
        if (in_array('cms_versions_tenant_id_data_theme_id_index', $indexes)) {
            return;
        }

        // Drop standalone filter indexes (replaced by tenant composites below)
        if (in_array($driver, ['mysql', 'mariadb'])) {
            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->dropIndex('cms_versions_data_theme_index');
                $table->dropIndex('cms_versions_data_status_index');
                $table->dropIndex('cms_versions_data_cache_index');
                $table->dropIndex('cms_versions_data_type_index');
                $table->dropIndex('cms_versions_data_scheduled_index');
                $table->dropIndex('cms_versions_data_name_index');
                $table->dropIndex('cms_versions_editor_index');
                $table->dropIndex('cms_versions_lang_index');
                $table->dropIndex('cms_versions_editor_id_index');
            });

            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->index(['data_theme', 'tenant_id', 'id'], 'cms_versions_data_theme_tenant_id_id_index');
                $table->index(['data_status', 'tenant_id', 'id'], 'cms_versions_data_status_tenant_id_id_index');
                $table->index(['data_cache', 'tenant_id', 'id'], 'cms_versions_data_cache_tenant_id_id_index');
                $table->index(['data_type', 'tenant_id', 'id'], 'cms_versions_data_type_tenant_id_id_index');
                $table->index(['data_scheduled', 'tenant_id', 'id'], 'cms_versions_data_scheduled_tenant_id_id_index');
                $table->index(['data_name', 'tenant_id', 'id'], 'cms_versions_data_name_tenant_id_id_index');
                $table->index(['lang', 'tenant_id', 'id'], 'cms_versions_lang_tenant_id_id_index');
                $table->index(['editor', 'tenant_id', 'id'], 'cms_versions_editor_tenant_id_id_index');
            });
        } elseif ($driver === 'pgsql') {
            // Drop existing (expression, id) indexes from migration 400000/500000
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_scheduled_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_name_id_index');

            // Drop standalone indexes from earlier migrations
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_lang_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_id_index');

            // Create (expression, tenant_id, id) composites
            $db->statement("CREATE INDEX cms_versions_data_theme_tenant_id_id_index ON cms_versions ((data->>'theme'), tenant_id, id)");
            $db->statement("CREATE INDEX cms_versions_data_status_tenant_id_id_index ON cms_versions ((data->>'status'), tenant_id, id)");
            $db->statement("CREATE INDEX cms_versions_data_cache_tenant_id_id_index ON cms_versions ((data->>'cache'), tenant_id, id)");
            $db->statement("CREATE INDEX cms_versions_data_type_tenant_id_id_index ON cms_versions ((data->>'type'), tenant_id, id)");
            $db->statement("CREATE INDEX cms_versions_data_scheduled_tenant_id_id_index ON cms_versions ((data->>'scheduled'), tenant_id, id)");
            $db->statement("CREATE INDEX cms_versions_data_name_tenant_id_id_index ON cms_versions ((data->>'name'), tenant_id, id)");
            $db->statement('CREATE INDEX cms_versions_lang_tenant_id_id_index ON cms_versions (lang, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_editor_tenant_id_id_index ON cms_versions (editor, tenant_id, id)');
        } elseif ($driver === 'sqlsrv') {
            // Drop existing (data_*, id) indexes from migration 500000
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_scheduled_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_name_id_index ON cms_versions');

            // Drop standalone indexes from earlier migrations
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_lang_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_id_index ON cms_versions');

            // Create (data_*, tenant_id, id) composites
            $db->statement('CREATE INDEX cms_versions_data_theme_tenant_id_id_index ON cms_versions (data_theme, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_data_status_tenant_id_id_index ON cms_versions (data_status, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_data_cache_tenant_id_id_index ON cms_versions (data_cache, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_data_type_tenant_id_id_index ON cms_versions (data_type, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_data_scheduled_tenant_id_id_index ON cms_versions (data_scheduled, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_data_name_tenant_id_id_index ON cms_versions (data_name, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_lang_tenant_id_id_index ON cms_versions (lang, tenant_id, id)');
            $db->statement('CREATE INDEX cms_versions_editor_tenant_id_id_index ON cms_versions (editor, tenant_id, id)');
        }
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

        if (in_array($driver, ['mysql', 'mariadb'])) {
            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->dropIndex('cms_versions_data_theme_tenant_id_id_index');
                $table->dropIndex('cms_versions_data_status_tenant_id_id_index');
                $table->dropIndex('cms_versions_data_cache_tenant_id_id_index');
                $table->dropIndex('cms_versions_data_type_tenant_id_id_index');
                $table->dropIndex('cms_versions_data_scheduled_tenant_id_id_index');
                $table->dropIndex('cms_versions_data_name_tenant_id_id_index');
                $table->dropIndex('cms_versions_lang_tenant_id_id_index');
                $table->dropIndex('cms_versions_editor_tenant_id_id_index');
            });

            // Restore standalone indexes
            Schema::connection($name)->table('cms_versions', function (Blueprint $table) {
                $table->index('data_theme');
                $table->index('data_status');
                $table->index('data_cache');
                $table->index('data_type');
                $table->index('data_scheduled', 'cms_versions_data_scheduled_index');
                $table->index('data_name', 'cms_versions_data_name_index');
                $table->index('editor');
                $table->index('lang', 'cms_versions_lang_index');
                $table->index(['editor', 'id'], 'cms_versions_editor_id_index');
            });
        } elseif ($driver === 'pgsql') {
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_scheduled_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_name_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_lang_tenant_id_id_index');
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_tenant_id_id_index');

            // Restore (expression, id) indexes
            $db->statement("CREATE INDEX cms_versions_data_theme_id_index ON cms_versions ((data->>'theme'), id)");
            $db->statement("CREATE INDEX cms_versions_data_status_id_index ON cms_versions ((data->>'status'), id)");
            $db->statement("CREATE INDEX cms_versions_data_cache_id_index ON cms_versions ((data->>'cache'), id)");
            $db->statement("CREATE INDEX cms_versions_data_type_id_index ON cms_versions ((data->>'type'), id)");
            $db->statement("CREATE INDEX cms_versions_data_scheduled_id_index ON cms_versions ((data->>'scheduled'), id)");
            $db->statement("CREATE INDEX cms_versions_data_name_id_index ON cms_versions ((data->>'name'), id)");
            $db->statement('CREATE INDEX cms_versions_editor_index ON cms_versions (editor)');
            $db->statement('CREATE INDEX cms_versions_lang_index ON cms_versions (lang)');
            $db->statement('CREATE INDEX cms_versions_editor_id_index ON cms_versions (editor, id)');
        } elseif ($driver === 'sqlsrv') {
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_theme_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_status_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_cache_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_type_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_scheduled_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_data_name_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_lang_tenant_id_id_index ON cms_versions');
            $db->statement('DROP INDEX IF EXISTS cms_versions_editor_tenant_id_id_index ON cms_versions');

            // Restore standalone + (data_*, id) indexes
            $db->statement('CREATE INDEX cms_versions_data_theme_id_index ON cms_versions (data_theme, id)');
            $db->statement('CREATE INDEX cms_versions_data_status_id_index ON cms_versions (data_status, id)');
            $db->statement('CREATE INDEX cms_versions_data_cache_id_index ON cms_versions (data_cache, id)');
            $db->statement('CREATE INDEX cms_versions_data_type_id_index ON cms_versions (data_type, id)');
            $db->statement('CREATE INDEX cms_versions_data_scheduled_id_index ON cms_versions (data_scheduled, id)');
            $db->statement('CREATE INDEX cms_versions_data_name_id_index ON cms_versions (data_name, id)');
            $db->statement('CREATE INDEX cms_versions_editor_index ON cms_versions (editor)');
            $db->statement('CREATE INDEX cms_versions_lang_index ON cms_versions (lang)');
            $db->statement('CREATE INDEX cms_versions_editor_id_index ON cms_versions (editor, id)');
        }
    }
};
