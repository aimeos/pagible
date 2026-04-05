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
        $name = config('cms.db', 'sqlite');
        $db = DB::connection($name);
        $driver = $db->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $db->statement('CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id, lang, editor, data_status)');
        } elseif ($driver === 'pgsql') {
            $db->statement('CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id) INCLUDE (lang, editor)');
        } elseif ($driver === 'sqlsrv') {
            $db->statement('CREATE INDEX cms_versions_id_covering_index ON cms_versions (id, tenant_id) INCLUDE (lang, editor, data_status)');
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
            $db->statement('DROP INDEX cms_versions_id_covering_index ON cms_versions');
        } elseif ($driver === 'pgsql') {
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_covering_index');
        } elseif ($driver === 'sqlsrv') {
            $db->statement('DROP INDEX IF EXISTS cms_versions_id_covering_index ON cms_versions');
        }
    }
};
