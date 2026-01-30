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
        $schema = Schema::connection(config('cms.db', 'sqlite'));

        if( in_array( $schema->getColumnType('cms_page_search', 'id'), ['varchar', 'char', 'guid', 'uuid'] ) ) {
            return;
        }

        // Add UUID columns

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Add UUID values

        DB::table('cms_page_search')
            ->whereNull('uuid')
            ->orderBy('id')
            ->chunkById(500, function ($models) {
                foreach ($models as $model) {
                    DB::table('cms_page_search')
                        ->where('id', $model->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // Remove old primary key

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        // Promote UUIDs to primary key

        $schema->table('cms_page_search', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
            $table->uuid('id')->primary()->change();
        });
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
