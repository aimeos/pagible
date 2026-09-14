<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    protected const INDEX = 'cms_pages_news_sitemap_index';


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( $schema->hasIndex( 'cms_pages', self::INDEX ) ) {
            $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->dropIndex( self::INDEX ) );
        }
    }


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        if( !$schema->hasIndex( 'cms_pages', self::INDEX ) ) {
            $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->index(
                ['tenant_id', 'type', 'deleted_at', 'created_at'], self::INDEX
            ) );
        }
    }
};
