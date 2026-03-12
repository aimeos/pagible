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
            $table->index(['tenant_id', 'status', '_lft', '_rgt']);
            $table->index(['tenant_id', 'deleted_at', 'depth', '_lft']);
            $table->index(['tenant_id', 'deleted_at', '_lft', '_rgt']);
            $table->dropIndex(['_lft', '_rgt', 'tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(config('cms.db', 'sqlite'))->table('cms_pages', function (Blueprint $table) {
            $table->index(['_lft', '_rgt', 'tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'status', '_lft', '_rgt']);
            $table->dropIndex(['tenant_id', 'deleted_at', 'depth', '_lft']);
            $table->dropIndex(['tenant_id', 'deleted_at', '_lft', '_rgt']);
        });
    }
};
