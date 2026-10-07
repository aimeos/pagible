<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\Models\PageVariant;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;


/**
 * Base query builder for the page facade
 *
 * Pages are stored in two tables: cms_pages contains the tree structure and
 * cms_page_variants one row per page and language. The builder reads from a
 * derived table aliased "cms_pages" which joins both tables, so existing
 * queries using unqualified or "cms_pages." qualified columns continue to
 * work. Writes are split up and applied to the underlying tables.
 */
class PageBuilder extends Builder
{
    /** @var list<string> Columns stored in the cms_pages table */
    public const PAGE_COLUMNS = ['id', 'tenant_id', 'parent_id', '_lft', '_rgt', 'depth', 'source', 'deleted_at'];

    /** @var array<string, string> Facade columns stored in the cms_page_variants table and their real names */
    public const VARIANT_COLUMNS = [
        'variant_id' => 'id', 'lang' => 'lang', 'domain' => 'domain', 'path' => 'path', 'to' => 'to',
        'name' => 'name', 'title' => 'title', 'type' => 'type', 'theme' => 'theme', 'tag' => 'tag',
        'cache' => 'cache', 'status' => 'status', 'meta' => 'meta', 'config' => 'config', 'content' => 'content',
        'hashes' => 'hashes', 'stale' => 'stale', 'latest_id' => 'latest_id', 'editor' => 'editor',
        'variant_deleted_at' => 'deleted_at',
    ];

    /** @var list<string> Columns stored in both tables */
    public const SHARED_COLUMNS = ['created_at', 'updated_at'];

    private const SIMPLE = ['Basic', 'In', 'NotIn', 'InRaw', 'NotInRaw', 'Null', 'NotNull', 'between', 'Bitwise'];

    /** @var string|null SQL of the derived page table */
    protected ?string $derivedSql = null;

    /** @var string|null Variant ID if the derived table joins a single variant */
    protected ?string $variantId = null;


    /**
     * Uses the variant joined by the given condition.
     *
     * @param string $mode "source" for the source language, "lang" for one language, "fallback" for one language
     *  or the source language if a page has no variant in that language, "variant" for one variant ID or "all"
     * @param string|null $value Language code or variant ID depending on the mode
     * @param bool $trashed Include soft-deleted variants
     * @return static Same builder for fluent interface
     */
    public function variants( string $mode = 'source', ?string $value = null, bool $trashed = false ) : static
    {
        $cols = [];
        foreach( self::PAGE_COLUMNS as $col ) {
            $cols[] = 'p.' . $col;
        }

        foreach( self::VARIANT_COLUMNS as $alias => $col ) {
            $cols[] = $alias === $col ? 'v.' . $col : 'v.' . $col . ' as ' . $alias;
        }

        foreach( self::SHARED_COLUMNS as $col ) {
            $cols[] = 'v.' . $col;
        }

        $sub = $this->newQuery()->from( 'cms_pages as p' )->select( $cols )
            ->join( 'cms_page_variants as v', function( $join ) use ( $mode, $value, $trashed ) {
                $join->on( 'v.page_id', '=', 'p.id' );

                switch( $mode )
                {
                    case 'source':
                        $join->on( 'v.lang', '=', 'p.source' );
                        break;
                    case 'lang':
                        $join->where( 'v.lang', '=', (string) $value );
                        break;
                    case 'fallback':
                        $join->where( function( $q ) use ( $value, $trashed ) {
                            $q->where( 'v.lang', '=', (string) $value )->orWhere( function( $q ) use ( $value, $trashed ) {
                                $q->whereColumn( 'v.lang', '=', 'p.source' )->whereNotExists( function( $q ) use ( $value, $trashed ) {
                                    $q->selectRaw( '1' )->from( 'cms_page_variants as w' )
                                        ->whereColumn( 'w.page_id', '=', 'p.id' )
                                        ->where( 'w.lang', '=', (string) $value )
                                        ->when( !$trashed, fn( $q ) => $q->whereNull( 'w.deleted_at' ) );
                                } );
                            } );
                        } );
                        break;
                    case 'variant':
                        $join->where( 'v.id', '=', (string) $value );
                        $trashed = true;
                        break;
                    case 'all':
                        break;
                    default:
                        throw new \InvalidArgumentException( sprintf( 'Invalid page variant mode "%1$s"', $mode ) );
                }

                if( !$trashed && $mode !== 'source' ) {
                    $join->whereNull( 'v.deleted_at' );
                }
            } );

        $this->bindings['from'] = [];
        $this->fromSub( $sub, 'cms_pages' );

        $this->derivedSql = $this->from instanceof Expression ? (string) $this->from->getValue( $this->getGrammar() ) : null;
        $this->variantId = $mode === 'variant' ? (string) $value : null;

        return $this;
    }


    /**
     * Deletes the selected pages including all their variants and versions.
     *
     * @param mixed $id Page ID or NULL to delete the matched records
     * @return int Number of deleted pages
     */
    public function delete( $id = null )
    {
        if( !$this->derived() ) {
            return parent::delete( $id );
        }

        if( $id !== null ) {
            $this->where( 'cms_pages.id', '=', $id );
        }

        if( empty( $this->joins ) && $this->pageOnly( $this->wheres ) )
        {
            // delete without fetching the IDs first
            $query = clone $this;
            $query->from = 'cms_pages';
            $query->bindings['from'] = [];

            $pages = ( clone $query )->select( 'cms_pages.id' );
            $pages->orders = $pages->limit = $pages->offset = null;
            $pages->bindings['order'] = [];
            $variants = $this->table( 'cms_page_variants' )->select( 'id' )->whereIn( 'page_id', $pages );

            $this->table( 'cms_versions' )
                ->where( 'versionable_type', PageVariant::class )
                ->whereIn( 'versionable_id', $variants )
                ->delete();

            $this->table( 'cms_page_variants' )->whereIn( 'page_id', $pages )->delete();

            return $query->baseDelete();
        }

        $ids = array_values( array_unique( array_column( $this->rows()->all(), 'id' ) ) );

        foreach( array_chunk( $ids, 500 ) as $chunk )
        {
            $vids = $this->table( 'cms_page_variants' )->whereIn( 'page_id', $chunk )->pluck( 'id' )->all();

            foreach( array_chunk( $vids, 500 ) as $vchunk )
            {
                $this->table( 'cms_versions' )
                    ->where( 'versionable_type', PageVariant::class )
                    ->whereIn( 'versionable_id', $vchunk )
                    ->delete();
            }

            $this->table( 'cms_page_variants' )->whereIn( 'page_id', $chunk )->delete();
            $this->table( 'cms_pages' )->whereIn( 'id', $chunk )->delete();
        }

        return count( $ids );
    }


    /**
     * Inserts new pages together with their variants.
     *
     * @param array<int|string, mixed> $values Single record or list of records
     * @return bool TRUE on success
     */
    public function insert( array $values )
    {
        if( !$this->derived() ) {
            return parent::insert( $values );
        }

        if( empty( $values ) ) {
            return true;
        }

        if( !is_array( reset( $values ) ) ) {
            $values = [$values];
        }

        $pages = $variants = [];

        foreach( $values as $row )
        {
            $row = $this->unqualify( $row );
            [$page, $variant] = $this->split( $row );

            $page['source'] ??= $variant['lang'] ?? '';
            $variant['id'] ??= $page['id'];
            $variant['page_id'] = $page['id'];
            $variant['tenant_id'] = $page['tenant_id'] ?? '';
            $variant['hashes'] ??= '{}';

            $pages[] = $page;
            $variants[] = $variant;
        }

        return $this->table( 'cms_pages' )->insert( $pages )
            && $this->table( 'cms_page_variants' )->insert( $variants );
    }


    /**
     * Inserting with auto-increment IDs isn't supported for pages.
     *
     * @param array<string, mixed> $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId( array $values, $sequence = null )
    {
        if( !$this->derived() ) {
            return parent::insertGetId( $values, $sequence );
        }

        throw new \LogicException( 'Pages use UUIDs, use insert() instead' );
    }


    /**
     * Updates the selected pages and their variants.
     *
     * @param array<string, mixed> $values Column/value pairs
     * @return int Number of affected records
     */
    public function update( array $values )
    {
        if( !$this->derived() ) {
            return parent::update( $values );
        }

        $values = $this->unqualify( $values );
        [$page, $variant] = $this->split( $values );

        // the timestamps of the pages only change if the page structure changes
        if( !empty( $variant ) && empty( array_diff_key( $page, array_flip( self::SHARED_COLUMNS ) ) ) ) {
            $page = [];
        }

        // and the timestamps of the variants only if their content changes
        if( !empty( $page ) && empty( array_diff_key( $variant, array_flip( self::SHARED_COLUMNS ) ) ) ) {
            $variant = [];
        }

        if( !isset( $variant['lang'] ) && ( $id = $this->pageKey() ) !== null && $this->variantId !== null )
        {
            // saving a single page variant doesn't require to look up the IDs
            $count = 0;

            if( !empty( $page ) ) {
                $count = $this->table( 'cms_pages' )->where( 'id', $id )->update( $page );
            }

            if( !empty( $variant ) ) {
                $count = $this->table( 'cms_page_variants' )->where( 'id', $this->variantId )->where( 'page_id', $id )->update( $variant );
            }

            return $count;
        }

        if( empty( $variant ) && empty( $this->joins ) && $this->pageOnly( $this->wheres ) )
        {
            $query = clone $this;
            $query->from = 'cms_pages';
            $query->bindings['from'] = [];

            return $query->baseUpdate( $page );
        }

        $rows = $this->rows();

        foreach( $rows->chunk( 500 ) as $chunk )
        {
            if( !empty( $page ) ) {
                $this->table( 'cms_pages' )->whereIn( 'id', $chunk->pluck( 'id' )->unique()->values()->all() )->update( $page );
            }

            if( !empty( $variant ) ) {
                $this->table( 'cms_page_variants' )->whereIn( 'id', $chunk->pluck( 'variant_id' )->all() )->update( $variant );
            }

            if( isset( $variant['lang'] ) && !( $variant['lang'] instanceof Expression ) )
            {
                $ids = $chunk->filter( fn( $row ) => $row->lang === $row->source )->pluck( 'id' )->all();

                if( !empty( $ids ) ) {
                    $this->table( 'cms_pages' )->whereIn( 'id', $ids )->update( ['source' => $variant['lang']] );
                }
            }
        }

        return $rows->count();
    }


    /**
     * Runs the delete of the parent class.
     *
     * @return int Number of deleted records
     */
    protected function baseDelete() : int
    {
        return parent::delete();
    }


    /**
     * Runs the update of the parent class.
     *
     * @param array<string, mixed> $values Column/value pairs
     * @return int Number of affected records
     */
    protected function baseUpdate( array $values ) : int
    {
        return parent::update( $values );
    }


    /**
     * Tests if the builder reads from the derived page table.
     */
    protected function derived() : bool
    {
        return $this->derivedSql !== null && $this->from instanceof Expression
            && $this->from->getValue( $this->getGrammar() ) === $this->derivedSql;
    }


    /**
     * Returns the page ID if the query matches a single page by its ID only.
     *
     * @return mixed Page ID or NULL if the query isn't restricted to one page ID
     */
    protected function pageKey() : mixed
    {
        if( !empty( $this->joins ) || count( $this->wheres ) !== 1 ) {
            return null;
        }

        $where = $this->wheres[0];

        if( ( $where['type'] ?? '' ) !== 'Basic' || ( $where['operator'] ?? '' ) !== '='
            || !in_array( $where['column'] ?? null, ['id', 'cms_pages.id'], true )
            || $where['value'] instanceof Expression
        ) {
            return null;
        }

        return $where['value'];
    }


    /**
     * Tests if the where conditions only reference columns of the cms_pages table.
     *
     * @param array<int, array<string, mixed>> $wheres List of where conditions
     */
    protected function pageOnly( array $wheres ) : bool
    {
        foreach( $wheres as $where )
        {
            $type = $where['type'] ?? '';

            if( $type === 'Nested' )
            {
                if( !$this->pageOnly( $where['query']->wheres ) ) {
                    return false;
                }
                continue;
            }

            if( $type === 'Column' )
            {
                if( !$this->pageColumn( $where['first'] ) || !$this->pageColumn( $where['second'] ) ) {
                    return false;
                }
                continue;
            }

            if( !in_array( $type, self::SIMPLE, true ) || !$this->pageColumn( $where['column'] ?? null ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the column is a column of the cms_pages table.
     */
    protected function pageColumn( mixed $column ) : bool
    {
        if( !is_string( $column ) ) {
            return false;
        }

        if( str_starts_with( $column, 'cms_pages.' ) ) {
            $column = substr( $column, 10 );
        }

        return in_array( $column, self::PAGE_COLUMNS, true );
    }


    /**
     * Returns the page and variant IDs of the selected records.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    protected function rows() : \Illuminate\Support\Collection
    {
        $query = clone $this;
        $query->columns = ['cms_pages.id', 'cms_pages.variant_id', 'cms_pages.lang', 'cms_pages.source'];
        $query->bindings['select'] = [];
        $query->aggregate = null;

        return $query->get();
    }


    /**
     * Splits the facade values into the values of the cms_pages and cms_page_variants tables.
     *
     * @param array<string, mixed> $values Column/value pairs
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} Page and variant values
     */
    protected function split( array $values ) : array
    {
        $page = $variant = [];

        foreach( $values as $key => $value )
        {
            if( in_array( $key, self::PAGE_COLUMNS, true ) ) {
                $page[$key] = $value;
            } elseif( in_array( $key, self::SHARED_COLUMNS, true ) ) {
                $page[$key] = $variant[$key] = $value;
            } elseif( isset( self::VARIANT_COLUMNS[$key] ) ) {
                $variant[self::VARIANT_COLUMNS[$key]] = $value;
            } else {
                throw new \InvalidArgumentException( sprintf( 'Unknown page column "%1$s"', $key ) );
            }
        }

        return [$page, $variant];
    }


    /**
     * Returns a plain query builder for the given table.
     */
    protected function table( string $table ) : Builder
    {
        return $this->connection->table( $table );
    }


    /**
     * Removes the "cms_pages." prefix from the keys.
     *
     * @param array<string, mixed> $values Column/value pairs
     * @return array<string, mixed> Unqualified column/value pairs
     */
    protected function unqualify( array $values ) : array
    {
        $result = [];

        foreach( $values as $key => $value ) {
            $result[str_starts_with( $key, 'cms_pages.' ) ? substr( $key, 10 ) : $key] = $value;
        }

        return $result;
    }
}
