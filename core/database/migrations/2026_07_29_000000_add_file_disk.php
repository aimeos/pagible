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
     * Keeps the compatibility migration irreversible because new installs own the columns in the base migration.
     */
    public function down(): void
    {
    }


    /**
     * Adds logical-disk storage and UUID-owner lookup columns to existing installations.
     */
    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( $schema->hasColumn( 'cms_files', 'disk' ) ) {
            return;
        }

        $schema->table( 'cms_files', function( Blueprint $table ) {
            $table->string( 'disk', 15 )->default( 'public' )->after( 'tenant_id' );
            $table->index( ['tenant_id', 'id'], 'cms_files_tenant_id_id_index' );
        } );
    }
};
