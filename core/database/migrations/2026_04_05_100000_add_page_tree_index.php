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
        $indexes = collect(Schema::connection($name)->getIndexes('cms_pages'))->pluck('name')->all();

        if( in_array('cms_pages_tenant_id_parent_id_deleted_at_index', $indexes) ) {
            return;
        }

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'tenant_id']);
            $table->index(['tenant_id', 'parent_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'parent_id', 'deleted_at']);
            $table->index(['parent_id', 'tenant_id']);
        });
    }
};
