<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Splits the language specific page data into one cms_page_variants row per page and language.
 *
 * The existing page row becomes the source variant and keeps its ID, so the version owner
 * (versionable_id) and the page/file and page/element pivot values remain valid.
 */
return new class extends Migration
{
    /**
     * Each step commits on its own and can be continued after an interruption, so the
     * large data copies don't run in one huge transaction on PostgreSQL and SQL Server.
     */
    public $withinTransaction = false;

    /**
     * @var list<string> Page columns moved to the variants
     *
     * SQLite rewrites the table for each dropped column, so the large ones are dropped first
     */
    private const MOVED = [
        'content', 'config', 'meta', 'editor', 'name', 'path', 'to', 'title', 'domain', 'lang',
        'tag', 'type', 'theme', 'cache', 'status', 'latest_id',
    ];


    public function down(): void
    {
        // irreversible, page variants can't be merged back into one row per page
    }


    public function up(): void
    {
        $name = config( 'cms.db', 'sqlite' );
        $schema = Schema::connection( $name );
        $db = DB::connection( $name );

        // all steps skip the work already done to continue an interrupted migration
        if( !$schema->hasTable( 'cms_page_variants' ) ) {
            $this->variants( $schema, $db );
        }

        // prefix searches for paths can't use the other indexes if the collation isn't "C"
        if( $db->getDriverName() === 'pgsql' ) {
            $db->statement( 'CREATE INDEX IF NOT EXISTS cms_page_variants_path_pattern_index ON cms_page_variants (domain, tenant_id, path varchar_pattern_ops)' );
        }

        if( $schema->hasColumn( 'cms_pages', 'lang' ) ) {
            $this->copy( $db );
        }

        $this->pivot( $schema, $db, 'cms_page_element', 'element_id', 'cms_elements' );
        $this->pivot( $schema, $db, 'cms_page_file', 'file_id', 'cms_files' );
        $this->pages( $schema, $db );
        $this->langs( $schema, $db );
        $this->versions( $db );
    }


    /**
     * Copies the language specific page data to the source variants.
     */
    private function copy( Connection $db ): void
    {
        $cols = [
            'tenant_id', 'lang', 'domain', 'path', 'to', 'name', 'title', 'type', 'theme', 'tag',
            'cache', 'status', 'meta', 'config', 'content', 'latest_id', 'editor', 'created_at', 'updated_at',
        ];

        // in batches to limit the transaction size for sites with many pages, already copied pages are skipped
        $db->table( 'cms_pages' )->select( 'id' )
            ->whereNotExists( fn( $q ) => $q->selectRaw( '1' )->from( 'cms_page_variants' )
                ->whereColumn( 'cms_page_variants.page_id', 'cms_pages.id' ) )
            ->chunkById( 1000, fn( $rows ) => $db->table( 'cms_page_variants' )->insertUsing(
                ['id', 'page_id', ...$cols, 'hashes'],
                $db->table( 'cms_pages' )->select( ['id', 'id as page_id', ...$cols] )->selectRaw( "'{}' as hashes" )
                    ->whereIn( 'id', $rows->pluck( 'id' )->all() )
            ) );
    }


    /**
     * Assigns the page versions to the page variants which have the same IDs as their pages.
     *
     * Updates in batches to limit the undo log and lock escalation for sites with many versions.
     * Can be continued after interruptions because only versions of the old type are updated.
     */
    private function versions( Connection $db ): void
    {
        // walks the primary key of all versions because filtering by type can make the database
        // use the type index and sort the remaining versions for each batch
        // SQL Server allows 2100 parameters per statement at most
        $db->table( 'cms_versions' )->select( 'id' )
            ->chunkById( 2000, fn( $rows ) => $db->table( 'cms_versions' )->whereIn( 'id', $rows->pluck( 'id' )->all() )
                ->where( 'versionable_type', 'Aimeos\Cms\Models\Page' )
                ->update( ['versionable_type' => 'Aimeos\Cms\Models\PageVariant'] ) );
    }


    /**
     * Drops the language specific columns and indexes from the pages table.
     */
    private function pages( Builder $schema, Connection $db ): void
    {
        if( !$schema->hasColumn( 'cms_pages', 'source' ) ) {
            $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->string( 'source', 10 )->default( '' ) );
        }

        if( $schema->hasColumn( 'cms_pages', 'lang' ) )
        {
            // in batches to limit the transaction size for sites with many pages
            $db->table( 'cms_pages' )->select( 'id' )->chunkById( 1000, fn( $rows ) => $db->table( 'cms_pages' )
                ->whereIn( 'id', $rows->pluck( 'id' )->all() )
                ->update( ['source' => $db->raw( $db->getQueryGrammar()->wrap( 'lang' ) )] ) );
        }

        foreach( $schema->getIndexes( 'cms_pages' ) as $index )
        {
            if( !( $index['primary'] ?? false ) && array_intersect( $index['columns'], self::MOVED ) ) {
                $this->dropIndex( $schema, $db, 'cms_pages', $index['name'], (bool) ( $index['unique'] ?? false ) );
            }
        }

        $moved = array_values( array_filter( self::MOVED, fn( $col ) => $schema->hasColumn( 'cms_pages', $col ) ) );

        if( $moved ) {
            $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->dropColumn( $moved ) );
        }

        $names = array_column( $schema->getIndexes( 'cms_pages' ), 'name' );

        $schema->table( 'cms_pages', function( Blueprint $table ) use ( $db, $names ) {
            if( !in_array( 'cms_pages_deleted_at_tenant_id__lft__rgt_index', $names, true ) ) {
                $table->index( ['deleted_at', 'tenant_id', '_lft', '_rgt'] );
            }

            if( $db->getDriverName() === 'sqlite' && !in_array( 'cms_pages_covering_index', $names, true ) ) {
                $table->index( ['deleted_at', 'tenant_id', 'depth', '_lft', '_rgt', 'id', 'parent_id', 'source'], 'cms_pages_covering_index' );
            }
        } );
    }


    /**
     * Drops an index or unique constraint.
     */
    private function dropIndex( Builder $schema, Connection $db, string $table, string $index, bool $unique ): void
    {
        $driver = $db->getDriverName();

        if( $driver === 'sqlite' ) {
            $db->statement( 'DROP INDEX IF EXISTS ' . $db->getQueryGrammar()->wrap( $index ) );
            return;
        }

        $schema->table( $table, fn( Blueprint $t ) => $unique ? $t->dropUnique( $index ) : $t->dropIndex( $index ) );
    }


    /**
     * Widens the language columns to support codes like "zh-Hant-TW".
     */
    private function langs( Builder $schema, Connection $db ): void
    {
        if( $db->getDriverName() === 'sqlite' ) {
            return; // SQLite doesn't enforce string lengths
        }

        foreach( ['cms_elements', 'cms_files', 'cms_versions'] as $name ) {
            $schema->table( $name, fn( Blueprint $table ) => $table->string( 'lang', 10 )->nullable()->change() );
        }
    }


    /**
     * Re-creates a page pivot table referencing the page variants instead of the pages.
     */
    private function pivot( Builder $schema, Connection $db, string $name, string $column, string $target ): void
    {
        $tmp = $name . '_tmp';

        // the old table is only dropped after the new one is complete
        if( $schema->hasTable( $name ) && !$schema->hasColumn( $name, 'variant_id' ) )
        {
            $schema->dropIfExists( $tmp );
            $this->create( $schema, $tmp, $name, $column, $target );

            // in batches to limit the transaction size, the variants have the same IDs as their pages
            $db->table( 'cms_pages' )->select( 'id' )->chunkById( 1000, fn( $rows ) => $db->table( $tmp )->insertUsing(
                ['variant_id', $column],
                $db->table( $name )->select( ['page_id', $column] )->whereIn( 'page_id', $rows->pluck( 'id' )->all() )
            ) );

            $schema->drop( $name );
        }

        if( !$schema->hasTable( $name ) ) {
            $schema->rename( $tmp, $name );
        }

        $names = array_column( $schema->getIndexes( $name ), 'name' );

        $schema->table( $name, function( Blueprint $table ) use ( $name, $column, $names ) {
            if( !in_array( $name . '_variant_id_' . $column . '_unique', $names, true ) ) {
                $table->unique( ['variant_id', $column] );
            }

            if( !in_array( $name . '_' . $column . '_index', $names, true ) ) {
                $table->index( $column );
            }
        } );
    }


    /**
     * Creates the new page pivot table referencing the page variants.
     */
    private function create( Builder $schema, string $tmp, string $name, string $column, string $target ): void
    {
        $schema->create( $tmp, function( Blueprint $table ) use ( $name, $column, $target ) {
            $table->uuid( 'variant_id' );
            $table->uuid( $column );

            $table->foreign( 'variant_id', $name . '_variant_fk' )->references( 'id' )->on( 'cms_page_variants' )
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign( $column, $name . '_' . $column . '_fk' )->references( 'id' )->on( $target )
                ->cascadeOnUpdate()->cascadeOnDelete();
        } );
    }


    /**
     * Creates the page variants table.
     */
    private function variants( Builder $schema, Connection $db ): void
    {
        $schema->create( 'cms_page_variants', function( Blueprint $table ) use ( $db ) {
            $table->uuid( 'id' )->primary();
            $table->uuid( 'page_id' );
            $table->string( 'lang', 10 );
            $table->string( 'tenant_id', 250 );
            $table->string( 'domain' );
            $table->string( 'path' );
            $table->string( 'to' );
            $table->string( 'name' );
            $table->string( 'title' );
            $table->string( 'type', 30 );
            $table->string( 'theme', 30 );
            $table->string( 'tag', 30 );
            $table->smallInteger( 'cache' );
            $table->smallInteger( 'status' );
            $table->json( 'meta' );
            $table->json( 'config' );
            $table->json( 'content' );
            $table->json( 'hashes' );
            $table->boolean( 'stale' )->default( false );
            $table->uuid( 'latest_id' )->nullable();
            $table->string( 'editor' );
            $table->softDeletes();
            $table->timestamps();

            $table->foreign( 'page_id' )->references( 'id' )->on( 'cms_pages' )->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique( ['page_id', 'lang', 'tenant_id'] );
            $table->unique( ['path', 'domain', 'tenant_id'] );
            $table->index( ['tenant_id', 'lang', 'stale'] );
            $table->index( ['tenant_id', 'lang', 'page_id'] );
            $table->index( ['tag', 'lang', 'tenant_id', 'status'] );
            $table->index( ['lang', 'tenant_id', 'status'] );
            $table->index( ['tenant_id', 'type', 'deleted_at', 'created_at'], 'cms_page_variants_news_sitemap_index' );
            $table->index( ['latest_id'] );
            $table->index( ['deleted_at', 'tenant_id'] );

            if( $db->getDriverName() === 'sqlite' ) {
                $table->index( ['page_id', 'lang', 'tenant_id', 'deleted_at', 'name', 'title', 'tag', 'path', 'domain', 'to', 'status', 'config', 'latest_id'], 'cms_page_variants_covering_index' );
            }
        } );

    }
};
