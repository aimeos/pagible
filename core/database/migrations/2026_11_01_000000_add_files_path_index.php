<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    protected const INDEX = 'cms_files_path_tenant_id_index';


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( $schema->hasIndex( 'cms_files', self::INDEX ) ) {
            $schema->table( 'cms_files', fn( Blueprint $table ) => $table->dropIndex( self::INDEX ) );
        }
    }


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        // files are looked up by their source URL, e.g. to reuse them in repeated imports
        if( !$schema->hasIndex( 'cms_files', self::INDEX ) ) {
            $schema->table( 'cms_files', fn( Blueprint $table ) => $table->index( ['path', 'tenant_id'], self::INDEX ) );
        }
    }
};
