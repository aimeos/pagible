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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->uuid('versionable_id');
            $table->string('versionable_type', 50);
            $table->boolean('published');
            $table->datetime('publish_at')->nullable();
            $table->string('lang', 5)->nullable();
            $table->json('data');
            $table->json('aux');
            $table->string('editor');
            $table->timestamp('created_at', 3);

            $table->index(['versionable_id', 'versionable_type', 'created_at', 'tenant_id'], 'idx_versions_id_type_created_tenantid');
            $table->index(['publish_at', 'published']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_versions');
    }
};
