<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $db->table( 'cms_versions' )->whereNotNull( 'publish_at' )
            ->chunkById( 500, function( $chunk ) use ( $db ) {
                $db->table( 'cms_versions' )->whereIn( 'id', $chunk->pluck( 'id' ) )
                    ->update( ['data->scheduled' => 1] );
            } );

        $db->table( 'cms_versions' )->whereNull( 'publish_at' )
            ->chunkById( 500, function( $chunk ) use ( $db ) {
                $db->table( 'cms_versions' )->whereIn( 'id', $chunk->pluck( 'id' ) )
                    ->update( ['data->scheduled' => 0] );
            } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // scheduled field in data JSON is harmless to leave in place
    }
};
