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

        if( in_array('cms_pages_tenant_id_parent_id_deleted_at__lft_index', $indexes) ) {
            return;
        }

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) use ($indexes) {
            if( in_array('cms_pages_tenant_id_parent_id_deleted_at_index', $indexes) ) {
                $table->dropIndex(['tenant_id', 'parent_id', 'deleted_at']);
            } elseif( in_array('cms_pages_parent_id_tenant_id_index', $indexes) ) {
                $table->dropIndex(['parent_id', 'tenant_id']);
            }
            $table->index(['tenant_id', 'parent_id', 'deleted_at', '_lft']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');

        Schema::connection($name)->table('cms_pages', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'parent_id', 'deleted_at', '_lft']);
            $table->index(['parent_id', 'tenant_id']);
        });
    }
};
