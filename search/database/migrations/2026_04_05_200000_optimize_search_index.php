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
     */
    public function up(): void
    {
        $name = config('cms.db', 'sqlite');

        if (Schema::connection($name)->getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::connection($name)->table('cms_index', function (Blueprint $table) {
            $table->index(['tenant_id', 'indexable_type', 'latest', 'indexable_id'], 'cms_index_tenant_type_latest_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');

        if (Schema::connection($name)->getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::connection($name)->table('cms_index', function (Blueprint $table) {
            $table->dropIndex('cms_index_tenant_type_latest_id');
        });
    }
};
