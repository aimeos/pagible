<?php

/**
 * @license MIT, https://opensource.org/license/mit
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
        Schema::connection(\Aimeos\Cms\DB::name())->dropIfExists('cms_version_element');
    }


    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection(\Aimeos\Cms\DB::name())->create('cms_version_element', function (Blueprint $table) {
            $table->foreignUuid('version_id')->constrained('cms_versions')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignUuid('element_id')->constrained('cms_elements')->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['version_id', 'element_id']);
        });
    }
};
