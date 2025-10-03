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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_page_element', function (Blueprint $table) {
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('element_id')->constrained('cms_elements')->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['page_id', 'element_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_page_element');
    }
};
