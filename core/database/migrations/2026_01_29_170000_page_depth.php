<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = config('cms.db', 'sqlite');
        $schema = Schema::connection($name);

        if( $schema->hasColumn('cms_pages', 'depth') ) {
            return;
        }

        $schema->table('cms_pages', function (Blueprint $table) {
            $table->nestedSetDepth(); // update table schema
        });

        \Aimeos\Cms\Models\Page::fixTree(); // update existing data
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // removed by previous migration
    }
};
