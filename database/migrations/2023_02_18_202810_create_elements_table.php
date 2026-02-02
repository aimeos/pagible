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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_elements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('type', 50);
            $table->string('lang', 5)->nullable();
            $table->string('name');
            $table->json('data');
            $table->string('editor');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'tenant_id']);
            $table->index(['lang', 'tenant_id']);
            $table->index(['name', 'tenant_id']);
            $table->index(['editor', 'tenant_id']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_elements');
    }
};
