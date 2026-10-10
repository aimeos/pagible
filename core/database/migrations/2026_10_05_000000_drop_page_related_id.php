<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Removes the related_id column of the pages, translations are page variants now.
 */
return new class extends Migration
{
    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( !$schema->hasColumn( 'cms_pages', 'related_id' ) ) {
            $schema->table( 'cms_pages', function( Blueprint $table ) {
                $table->uuid( 'related_id' )->nullable();
                $table->index( ['related_id', 'tenant_id'] );
            } );
        }
    }


    public function up(): void
    {
        $name = config( 'cms.db', 'sqlite' );
        $schema = Schema::connection( $name );
        $db = DB::connection( $name );

        if( !$schema->hasColumn( 'cms_pages', 'related_id' ) ) {
            return;
        }

        foreach( $schema->getIndexes( 'cms_pages' ) as $index )
        {
            if( !in_array( 'related_id', $index['columns'], true ) ) {
                continue;
            }

            if( $db->getDriverName() === 'sqlite' ) {
                $db->statement( 'DROP INDEX IF EXISTS ' . $db->getQueryGrammar()->wrap( $index['name'] ) );
            } else {
                $schema->table( 'cms_pages', fn( Blueprint $table ) => $index['unique']
                    ? $table->dropUnique( $index['name'] )
                    : $table->dropIndex( $index['name'] )
                );
            }
        }

        $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->dropColumn( 'related_id' ) );
    }
};
