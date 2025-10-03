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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_element_file', function (Blueprint $table) {
            $table->foreignUuid('element_id')->constrained('cms_elements')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('file_id')->constrained('cms_files')->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['element_id', 'file_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_element_file');
    }
};
