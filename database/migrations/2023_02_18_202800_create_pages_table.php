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
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_pages', function (Blueprint $table) {
            $table->id();
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
            $table->integer('related_id')->nullable();
            $table->json('meta');
            $table->json('config');
            $table->json('content');
            $table->string('editor');
            $table->softDeletes();
            $table->timestamps();
            $table->nestedSet();

            $table->unique(['path', 'domain', 'tenant_id']);
            $table->index(['_lft', '_rgt', 'tenant_id', 'status']);
            $table->index(['tag', 'lang', 'tenant_id', 'status']);
            $table->index(['lang', 'tenant_id', 'status']);
            $table->index(['related_id', 'tenant_id']);
            $table->index(['parent_id', 'tenant_id']);
            $table->index(['domain', 'tenant_id']);
            $table->index(['to', 'tenant_id']);
            $table->index(['name', 'tenant_id']);
            $table->index(['title', 'tenant_id']);
            $table->index(['type', 'tenant_id']);
            $table->index(['theme', 'tenant_id']);
            $table->index(['cache', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
            $table->index(['deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_pages');
    }
};
