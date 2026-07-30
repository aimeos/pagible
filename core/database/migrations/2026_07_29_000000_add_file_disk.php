<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_files', function( Blueprint $table ) {
            $table->dropIndex( 'cms_files_tenant_id_id_index' );
            $table->dropColumn( 'disk' );
        } );
    }


    public function up(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_files', function( Blueprint $table ) {
            $table->string( 'disk', 15 )->default( 'public' )->after( 'tenant_id' );
            $table->index( ['tenant_id', 'id'], 'cms_files_tenant_id_id_index' );
        } );
    }
};
