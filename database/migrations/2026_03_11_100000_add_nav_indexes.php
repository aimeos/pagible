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
        Schema::connection(config('cms.db', 'sqlite'))->table('cms_pages', function (Blueprint $table) {
            $table->dropIndex(['_lft', '_rgt', 'tenant_id', 'status']);

            $table->index(['tenant_id', 'status', '_lft', '_rgt']);
            $table->index(['tenant_id', 'depth', 'deleted_at', '_lft']);
            $table->index(['tenant_id', 'deleted_at', '_rgt', '_lft']);

            $driver = Schema::connection(config('cms.db', 'sqlite'))->getConnection()->getDriverName();

            if( $driver === 'sqlite' ) {
                $table->index(['tenant_id', 'deleted_at', 'depth', '_lft', '_rgt', 'id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config'], 'cms_pages_covering_index');
            } else {
                $table->index(['tenant_id', 'deleted_at', '_lft', '_rgt']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('cms.db', 'sqlite'))->table('cms_pages', function (Blueprint $table) {
            $table->index(['_lft', '_rgt', 'tenant_id', 'status']);

            $table->dropIndex(['tenant_id', 'deleted_at', '_rgt', '_lft']);
            $table->dropIndex(['tenant_id', 'depth', 'deleted_at', '_lft']);
            $table->dropIndex(['tenant_id', 'status', '_lft', '_rgt']);

            $driver = Schema::connection(config('cms.db', 'sqlite'))->getConnection()->getDriverName();

            if( $driver === 'sqlite' ) {
                $table->dropIndex('cms_pages_covering_index');
            } else {
                $table->dropIndex(['tenant_id', 'deleted_at', '_lft', '_rgt']);
            }
        });
    }
};
