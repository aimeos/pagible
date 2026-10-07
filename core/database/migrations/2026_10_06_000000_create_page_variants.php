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
    /** @var list<string> Page columns moved to the variants */
    private const MOVED = [
        'name', 'path', 'to', 'title', 'domain', 'lang', 'tag', 'type', 'theme', 'cache', 'status',
        'latest_id', 'meta', 'config', 'content', 'editor',
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

        if( $schema->hasTable( 'cms_page_variants' ) ) {
            return;
        }

        $this->variants( $schema, $db );
        $this->copy( $db );
        $this->pivot( $schema, $db, 'cms_page_element', 'element_id', 'cms_elements' );
        $this->pivot( $schema, $db, 'cms_page_file', 'file_id', 'cms_files' );
        $this->pages( $schema, $db );
        $this->langs( $schema, $db );

        $db->table( 'cms_versions' )
            ->where( 'versionable_type', 'Aimeos\Cms\Models\Page' )
            ->update( ['versionable_type' => 'Aimeos\Cms\Models\PageVariant'] );
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

        $db->table( 'cms_page_variants' )->insertUsing(
            ['id', 'page_id', ...$cols, 'hashes'],
            $db->table( 'cms_pages' )->select( ['id', 'id as page_id', ...$cols] )->selectRaw( "'{}' as hashes" )
        );
    }


    /**
     * Drops the language specific columns and indexes from the pages table.
     */
    private function pages( Builder $schema, Connection $db ): void
    {
        $schema->table( 'cms_pages', function( Blueprint $table ) {
            $table->string( 'source', 10 )->default( '' );
        } );

        $db->table( 'cms_pages' )->update( ['source' => $db->raw( $db->getQueryGrammar()->wrap( 'lang' ) )] );

        foreach( $schema->getIndexes( 'cms_pages' ) as $index )
        {
            if( !( $index['primary'] ?? false ) && array_intersect( $index['columns'], self::MOVED ) ) {
                $this->dropIndex( $schema, $db, 'cms_pages', $index['name'], (bool) ( $index['unique'] ?? false ) );
            }
        }

        $schema->table( 'cms_pages', fn( Blueprint $table ) => $table->dropColumn( self::MOVED ) );

        $names = array_column( $schema->getIndexes( 'cms_pages' ), 'name' );

        $schema->table( 'cms_pages', function( Blueprint $table ) use ( $db, $names ) {
            if( !in_array( 'cms_pages_deleted_at_tenant_id__lft__rgt_index', $names, true ) ) {
                $table->index( ['deleted_at', 'tenant_id', '_lft', '_rgt'] );
            }

            if( $db->getDriverName() === 'sqlite' ) {
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

        $schema->create( $tmp, function( Blueprint $table ) use ( $name, $column, $target ) {
            $table->uuid( 'variant_id' );
            $table->uuid( $column );

            $table->foreign( 'variant_id', $name . '_variant_fk' )->references( 'id' )->on( 'cms_page_variants' )
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign( $column, $name . '_' . $column . '_fk' )->references( 'id' )->on( $target )
                ->cascadeOnUpdate()->cascadeOnDelete();
        } );

        $db->table( $tmp )->insertUsing( ['variant_id', $column], $db->table( $name )->select( ['page_id', $column] ) );

        $schema->drop( $name );
        $schema->rename( $tmp, $name );

        $schema->table( $name, function( Blueprint $table ) use ( $column ) {
            $table->unique( ['variant_id', $column] );
            $table->index( $column );
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

            if( $db->getDriverName() === 'sqlite' ) {
                $table->index( ['page_id', 'lang', 'tenant_id', 'deleted_at', 'name', 'title', 'tag', 'path', 'domain', 'to', 'status', 'config', 'latest_id'], 'cms_page_variants_covering_index' );
            }
        } );
    }
};
