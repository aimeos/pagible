<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

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

        $schema->table('cms_versions', function (Blueprint $table) {
            $table->uuid('versionable_uuid')->nullable()->after('versionable_id');
            $table->dropIndex('idx_versions_id_type_created_tenantid');
        });

        DB::connection($name)->table('cms_versions')->update(['versionable_uuid' => DB::raw('cms_versions.versionable_id')]);
DB::enableQueryLog();
        DB::connection($name)->table('cms_versions')->update(['data->related_id' => null]);
print_r(DB::getQueryLog());

        $schema->dropColumns('cms_versions', 'versionable_id');

        $schema->table('cms_versions', function (Blueprint $table) {
            $table->renameColumn('versionable_uuid', 'versionable_id');
            $table->index(['versionable_id', 'versionable_type', 'created_at', 'tenant_id'], 'idx_versions_id_type_created_tenantid');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // removed by previous migration
    }
};
