<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    private const INDEX = 'cms_pages_id_tenant_id_unique';


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( $schema->hasIndex( 'cms_pages', self::INDEX, 'unique' ) ) {
            return;
        }

        $schema->table( 'cms_pages', function( Blueprint $table ) {
            $table->unique( ['id', 'tenant_id'], self::INDEX );
        } );
    }


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( !$schema->hasIndex( 'cms_pages', self::INDEX, 'unique' ) ) {
            return;
        }

        $schema->table( 'cms_pages', function( Blueprint $table ) {
            $table->dropUnique( self::INDEX );
        } );
    }
};
