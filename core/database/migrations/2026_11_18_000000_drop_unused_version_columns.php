<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Aimeos\Cms\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Versions are scheduled if they have a publish date and the file MIME type is
     * filtered by the cms_files.mime column, so their generated columns aren't used.
     */
    protected const FIELDS = ['scheduled', 'mime'];


    public function up(): void
    {
        $schema = Schema::connection( DB::name() );

        $indexes = array_intersect(
            array_map( fn( $field ) => "cms_versions_data_{$field}_tenant_id_id_index", self::FIELDS ),
            $schema->getIndexListing( 'cms_versions' )
        );
        $columns = array_intersect(
            array_map( fn( $field ) => "data_{$field}", self::FIELDS ),
            $schema->getColumnListing( 'cms_versions' )
        );

        if( !$indexes && !$columns ) {
            return;
        }

        // the indexes must be dropped before their columns
        $schema->table( 'cms_versions', function( Blueprint $table ) use ( $indexes, $columns ) {
            foreach( $indexes as $index ) {
                $table->dropIndex( $index );
            }

            if( $columns ) {
                $table->dropColumn( array_values( $columns ) );
            }
        } );
    }
};
