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
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( !$schema->hasIndex( 'cms_versions', 'cms_versions_scheduled_index' ) ) {
            $schema->table( 'cms_versions', function( Blueprint $table ) {
                $table->index( ['tenant_id', 'published', 'publish_at', 'created_at', 'id'], 'cms_versions_scheduled_index' );
            } );
        }

        if( $schema->hasIndex( 'cms_versions', 'cms_versions_publish_at_published_index' ) ) {
            $schema->table( 'cms_versions', function( Blueprint $table ) {
                $table->dropIndex( 'cms_versions_publish_at_published_index' );
            } );
        }
    }


    public function down(): void
    {
    }
};
