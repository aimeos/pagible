<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


/**
 * Adds the language of the page variants to the search index.
 *
 * Searches limited to one language filter the index rows before joining the pages.
 */
return new class extends Migration
{
    public $withinTransaction = false;


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );
        $db = $schema->getConnection();

        if( $schema->hasColumn( 'cms_index', 'indexable_lang' ) ) {
            return;
        }

        // columns can't be added to FTS5 tables, so the table is created again
        if( $db->getDriverName() === 'sqlite' )
        {
            $lang = '(SELECT v.lang FROM cms_page_variants v WHERE v.id = cms_index.indexable_id)';

            $db->statement( 'CREATE VIRTUAL TABLE cms_index_new USING fts5(
                indexable_id UNINDEXED,
                indexable_type UNINDEXED,
                tenant_id UNINDEXED,
                latest UNINDEXED,
                indexable_lang UNINDEXED,
                content
            )' );
            $db->insert( "INSERT INTO cms_index_new (indexable_id, indexable_type, tenant_id, latest, indexable_lang, content)
                SELECT indexable_id, indexable_type, tenant_id, latest, CASE WHEN indexable_type = ? THEN {$lang} END, content FROM cms_index", [Page::class] );
            $db->statement( 'DROP TABLE cms_index' );
            $db->statement( 'ALTER TABLE cms_index_new RENAME TO cms_index' );
            return;
        }

        $schema->table( 'cms_index', function( Blueprint $table ) {
            $table->string( 'indexable_lang', 10 )->nullable();
        } );

        // filled in short chunks per tenant and language, the index is added afterwards to keep the updates cheap
        $db->table( 'cms_page_variants' )->select( ['id', 'lang', 'tenant_id'] )
            ->chunkById( 1000, function( $variants ) use ( $db ) {
                foreach( $variants->groupBy( fn( $v ) => $v->tenant_id . "\0" . $v->lang ) as $list )
                {
                    $db->table( 'cms_index' )
                        ->where( 'tenant_id', $list->first()->tenant_id )
                        ->where( 'indexable_type', Page::class )
                        ->whereIn( 'latest', [false, true] )
                        ->whereIn( 'indexable_id', $list->pluck( 'id' )->all() )
                        ->update( ['indexable_lang' => $list->first()->lang] );
                }
            } );

        $schema->table( 'cms_index', function( Blueprint $table ) {
            $table->index( ['tenant_id', 'indexable_type', 'latest', 'indexable_lang'] );
        } );
    }


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        // the column is ignored by the previous code, FTS5 tables can't drop columns
        if( $schema->getConnection()->getDriverName() !== 'sqlite' && $schema->hasColumn( 'cms_index', 'indexable_lang' ) )
        {
            $schema->table( 'cms_index', function( Blueprint $table ) {
                $table->dropIndex( ['tenant_id', 'indexable_type', 'latest', 'indexable_lang'] );
                $table->dropColumn( 'indexable_lang' );
            } );
        }
    }
};
