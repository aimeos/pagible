<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->create( 'cms_page_access', function( Blueprint $table ) {
            $table->uuid( 'page_id' );
            $table->string( 'tenant_id', 250 );
            $table->string( 'value', 100 )->default( '' );
            $table->string( 'editor' );
            $table->timestamps();

            $table->primary( ['page_id', 'value'] );
            $table->foreign( ['page_id', 'tenant_id'] )
                ->references( ['id', 'tenant_id'] )->on( 'cms_pages' )->cascadeOnDelete();
        } );
    }


    public function down(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->dropIfExists( 'cms_page_access' );
    }
};
