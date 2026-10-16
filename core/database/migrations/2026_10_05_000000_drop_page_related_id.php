<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


/**
 * Removes the related_id column of the pages, translations are page variants now.
 */
return new class extends Migration
{
    public function down(): void
    {
    }


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( !$schema->hasColumn( 'cms_pages', 'related_id' ) ) {
            return;
        }

        // SQLite can't drop columns which are still part of an index
        foreach( $schema->getIndexes( 'cms_pages' ) as $index )
        {
            if( in_array( 'related_id', $index['columns'], true ) ) {
                $schema->table( 'cms_pages', fn( Blueprint $table ) => $index['unique']
                    ? $table->dropUnique( $index['name'] )
                    : $table->dropIndex( $index['name'] )
                );
            }
        }

        $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->dropColumn( 'related_id' ) );
    }
};
