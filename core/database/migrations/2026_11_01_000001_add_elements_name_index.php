<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    protected const INDEX = 'cms_elements_name_lang_tenant_id_index';


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( $schema->hasIndex( 'cms_elements', self::INDEX ) ) {
            $schema->table( 'cms_elements', fn( Blueprint $table ) => $table->dropIndex( self::INDEX ) );
        }
    }


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        // shared elements are looked up by their name and language, e.g. to reuse them in repeated imports
        if( !$schema->hasIndex( 'cms_elements', self::INDEX ) ) {
            $schema->table( 'cms_elements', fn( Blueprint $table ) => $table->index( ['name', 'lang', 'tenant_id'], self::INDEX ) );
        }
    }
};
