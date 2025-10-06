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
        Schema::connection(config('cms.db', 'sqlite'))->create('cms_page_search', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->bigIncrements('id');
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('tenant_id', 250);
            $table->string('lang', 5);
            $table->string('domain');
            $table->string('path');
            $table->string('title');
            $table->text('content');

            $table->unique(['tenant_id', 'lang', 'domain', 'path']);

            if(in_array(Schema::getConnection()->getDriverName(), ['mysql'])) {
                $table->fullText('content');
                $table->fullText('title');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection(config('cms.db', 'sqlite'))->dropIfExists('cms_page_search');
    }
};
