<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function down() : void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->dropIfExists( 'cms_webhooks' );
    }


    public function up() : void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->create( 'cms_webhooks', function( Blueprint $table ) {
            $table->uuid( 'id' )->primary();
            $table->string( 'tenant_id', 250 );
            $table->smallInteger( 'status' )->default( 0 );
            $table->unsignedBigInteger( 'revision' )->default( 1 );
            $table->string( 'name', 100 )->default( '' );
            $table->text( 'url' );
            $table->text( 'secrets' );
            $table->json( 'events' );
            $table->json( 'last_error' )->nullable();
            $table->timestamp( 'last_success_at' )->nullable();
            $table->string( 'editor' );
            $table->timestamps();

            $table->index( ['tenant_id', 'status'], 'idx_cms_webhooks_tenant_status' );
        } );
    }
};
