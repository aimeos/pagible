<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $name = config('cms.db', 'sqlite');
        $db = Schema::connection($name)->getConnection();

        if( $db->getDriverName() !== 'pgsql' ) {
            return;
        }

        $indexes = collect(Schema::connection($name)->getIndexes('cms_index'))->pluck('name')->all();

        if( in_array('cms_index_content_gin', $indexes) ) {
            return;
        }

        $db->statement("CREATE INDEX cms_index_content_gin ON cms_index USING gin(to_tsvector('simple', coalesce(content, '')))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $name = config('cms.db', 'sqlite');
        $db = Schema::connection($name)->getConnection();

        if( $db->getDriverName() !== 'pgsql' ) {
            return;
        }

        $indexes = collect(Schema::connection($name)->getIndexes('cms_index'))->pluck('name')->all();

        if( in_array('cms_index_content_gin', $indexes) ) {
            $db->statement('DROP INDEX cms_index_content_gin');
        }
    }
};
