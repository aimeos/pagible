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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_files', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('tenant_id');
            $table->string('mime', 100);
            $table->string('lang', 5)->nullable();
            $table->string('name');
            $table->string('path');
            $table->json('previews');
            $table->json('description');
            $table->json('transcription');
            $table->string('editor');
            $table->softDeletes();
            $table->timestamps();

            $table->primary('id');
            $table->index(['mime', 'tenant_id']);
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
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_files');
    }
};
