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
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_pages');
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = config('cms.db', 'sqlite');

        Schema::connection($name)->create('cms_pages', function (Blueprint $table) use ($name) {
            $table->uuid('id')->primary();
            $table->string('tenant_id', 250);
            $table->string('name');
            $table->string('path');
            $table->string('to');
            $table->string('title');
            $table->string('domain');
            $table->string('lang', 5);
            $table->string('tag', 30);
            $table->string('type', 30);
            $table->string('theme', 30);
            $table->smallInteger('cache');
            $table->smallInteger('status');
            $table->uuid('related_id')->nullable();
            $table->uuid('latest_id')->nullable();
            $table->json('meta');
            $table->json('config');
            $table->json('content');
            $table->string('editor');
            $table->softDeletes();
            $table->timestamps();
            $table->nestedSet('id', 'uuid');
            $table->nestedSetDepth();

            $table->unique(['path', 'domain', 'tenant_id']);
            $table->index(['tag', 'lang', 'tenant_id', 'status']);
            $table->index(['parent_id', 'deleted_at', 'tenant_id', '_lft']);
            $table->index(['lang', 'tenant_id', 'status']);
            $table->index(['domain', 'tenant_id']);
            $table->index(['status', 'tenant_id', '_lft', '_rgt']);
            $table->index(['depth', 'deleted_at', 'tenant_id', '_lft']);
            $table->index(['_lft', '_rgt', 'parent_id', 'tenant_id']);

            $driver = Schema::connection($name)->getConnection()->getDriverName();

            if( $driver === 'sqlite' ) {
                $table->index(['deleted_at', 'tenant_id', 'depth', '_lft', '_rgt', 'id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config'], 'cms_pages_covering_index');
            } else {
                $table->index(['deleted_at', 'tenant_id', '_lft', '_rgt', 'latest_id']);
            }
        });
    }
};
