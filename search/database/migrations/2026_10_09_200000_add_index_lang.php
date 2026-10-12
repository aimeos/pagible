<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


/**
 * Adds the language of the page variants to the search index.
 *
 * Searches limited to one language filter the index rows before joining the pages.
 * The page rows are indexed by the page view model instead of the page model now.
 */
return new class extends Migration
{
    /** @var string Model of the page rows in the index before */
    private const PAGE = 'Aimeos\\Cms\\Models\\Page';

    public $withinTransaction = false;


    public function up(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );
        $db = $schema->getConnection();

        if( $schema->hasColumn( 'cms_index', 'indexable_lang' ) )
        {
            $this->convert( $db );
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
                SELECT indexable_id, CASE WHEN indexable_type IN (?, ?) THEN ? ELSE indexable_type END, tenant_id, latest,
                    CASE WHEN indexable_type IN (?, ?) THEN {$lang} END, content FROM cms_index",
                [self::PAGE, PageView::class, PageView::class, self::PAGE, PageView::class] );
            $db->statement( 'DROP TABLE cms_index' );
            $db->statement( 'ALTER TABLE cms_index_new RENAME TO cms_index' );
            return;
        }

        $schema->table( 'cms_index', function( Blueprint $table ) {
            $table->string( 'indexable_lang', 10 )->nullable();
        } );

        // filled in short chunks per tenant and language, the index is added afterwards to keep the updates cheap
        // and the page model is replaced by the page view model in the same update, so each row is written once,
        // no cursor because some databases can't write while a result set of the same connection is open
        $db->table( 'cms_page_variants' )->select( ['id', 'lang', 'tenant_id'] )->chunkById( 1000, function( $chunk ) use ( $db ) {
            foreach( $chunk->groupBy( fn( $v ) => $v->tenant_id . "\0" . $v->lang ) as $list )
            {
                $db->table( 'cms_index' )
                    ->where( 'tenant_id', $list->first()->tenant_id )
                    ->whereIn( 'indexable_type', [self::PAGE, PageView::class] )
                    ->whereIn( 'latest', [false, true] )
                    ->whereIn( 'indexable_id', $list->pluck( 'id' )->all() )
                    ->update( ['indexable_type' => PageView::class, 'indexable_lang' => $list->first()->lang] );
            }
        } );

        // rows of pages without variants
        $this->convert( $db );

        $schema->table( 'cms_index', function( Blueprint $table ) {
            $table->index( ['tenant_id', 'indexable_type', 'latest', 'indexable_lang'] );
        } );
    }


    /**
     * Replaces the page model by the page view model in the remaining index rows.
     *
     * Updated in chunks per tenant using the index of the table, SQLite FTS5 tables
     * have no index and are updated at once.
     */
    private function convert( Connection $db ): void
    {
        if( $db->getDriverName() === 'sqlite' )
        {
            $db->table( 'cms_index' )->where( 'indexable_type', self::PAGE )->update( ['indexable_type' => PageView::class] );
            return;
        }

        foreach( $db->table( 'cms_index' )->distinct()->pluck( 'tenant_id' ) as $tenant )
        {
            $db->table( 'cms_index' )->select( 'id' )
                ->where( 'tenant_id', $tenant )
                ->where( 'indexable_type', self::PAGE )
                ->chunkById( 1000, fn( $rows ) => $db->table( 'cms_index' )
                    ->whereIn( 'id', $rows->pluck( 'id' )->all() )
                    ->update( ['indexable_type' => PageView::class] )
                );
        }
    }


    public function down(): void
    {
        $schema = Schema::connection( config( 'cms.db', 'sqlite' ) );

        $schema->getConnection()->table( 'cms_index' )->where( 'indexable_type', PageView::class )
            ->update( ['indexable_type' => self::PAGE] );

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
