<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\Models\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;


/**
 * Base query builder for the page facade
 *
 * Pages are stored in two tables: cms_pages contains the tree structure and
 * cms_page_variants one row per page and language. The builder reads from the
 * cms_page_view view joining both tables, aliased "cms_pages", so existing
 * queries using unqualified or "cms_pages." qualified columns continue to
 * work. A condition added by the Variant scope selects the variant of each
 * page. The view is read-only, the Resource class writes the tree columns to
 * cms_pages and the language specific ones to cms_page_variants.
 */
class PageBuilder extends Builder
{
    /** @var string Alias of the page view, the table name of the page model */
    public const ALIAS = 'cms_pages';

    /** @var string Name of the view joining the pages and their variants */
    public const VIEW = 'cms_page_view';

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

    /** @var array{0: string, 1: string|null, 2: bool}|null Mode, value and trashed flag of the variant condition */
    public ?array $variant = null;


    /**
     * Set the table which the query is targeting.
     *
     * The variant condition only applies to the page view, so it's removed if another table
     * is used. The nested set builds subqueries on the cms_pages table, e.g. for has('ancestors')
     * or whereAncestorOf(), and the condition would refer to the page view row of the outer query.
     *
     * @param \Closure|Builder|\Illuminate\Contracts\Database\Query\Expression|string $table
     * @param string|null $as
     * @return $this
     */
    public function from( $table, $as = null )
    {
        if( $this->variant !== null )
        {
            $this->wheres = self::withoutVariant( $this->wheres );
            $this->variant = null;
        }

        return parent::from( $table, $as );
    }


    /**
     * Uses the page view and selects the variant by the given mode.
     *
     * The condition selecting the variants is added by the Variant scope when the query is executed,
     * so the where clauses of the query are grouped and can't bypass the condition.
     *
     * @param string $mode "source" for the source language, "lang" for one language, "fallback" for one language
     *  or the source language if a page has no variant in that language, "visible" for one language or the source
     *  language if a page has no published and enabled variant in that language, "variant" for one variant ID or "all"
     * @param string|null $value Language code or variant ID depending on the mode
     * @param bool $trashed Include soft-deleted variants
     * @return static Same builder for fluent interface
     */
    public function variants( string $mode = 'source', ?string $value = null, bool $trashed = false ) : static
    {
        if( !in_array( $mode, ['source', 'lang', 'fallback', 'visible', 'variant', 'all'], true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid page variant mode "%1$s"', $mode ) );
        }

        $this->from( self::VIEW, self::ALIAS );
        $this->variant = [$mode, $value, $trashed];

        return $this;
    }


    /**
     * Adds the condition selecting the variants of the pages in the page view.
     *
     * @return static Same builder for fluent interface
     */
    public function whereVariant() : static
    {
        if( $this->variant === null ) {
            return $this;
        }

        // an already applied condition is replaced
        $this->wheres = self::withoutVariant( $this->wheres );

        [$mode, $value, $trashed] = $this->variant;
        $col = fn( string $name ) => self::ALIAS . '.' . $name;
        $cond = $this->forNestedWhere();
        $literal = $this->literal( $value );

        switch( $mode )
        {
            case 'source':
                $cond->whereColumn( $col( 'lang' ), '=', $col( 'source' ) );
                $trashed = true;
                break;
            case 'lang':
                $cond->where( $col( 'lang' ), '=', $literal );
                break;
            case 'fallback':
            case 'visible':
                // "visible" only uses published and enabled variants of the language
                $visible = $mode === 'visible';
                $trashed = $trashed && !$visible;

                $cond->where( function( $q ) use ( $col, $literal, $visible, $trashed ) {
                    $q->where( function( $q ) use ( $col, $literal, $visible ) {
                        $q->where( $col( 'lang' ), '=', $literal )
                            ->when( $visible, fn( $q ) => $q->where( $col( 'status' ), '<>', new Expression( '0' ) ) );
                    } )->orWhere( function( $q ) use ( $col, $literal, $visible, $trashed ) {
                        $q->whereColumn( $col( 'lang' ), '=', $col( 'source' ) )->whereNotExists( function( $q ) use ( $col, $literal, $visible, $trashed ) {
                            $q->selectRaw( '1' )->from( 'cms_page_variants as w' )
                                ->whereColumn( 'w.page_id', '=', $col( 'id' ) )
                                ->where( 'w.lang', '=', $literal )
                                ->when( $visible, fn( $q ) => $q->where( 'w.status', '<>', new Expression( '0' ) ) )
                                ->when( !$trashed, fn( $q ) => $q->whereNull( 'w.deleted_at' ) );
                        } );
                    } );
                } );
                break;
            case 'variant':
                $cond->where( $col( 'variant_id' ), '=', $literal );
                $trashed = true;
                break;
        }

        if( !$trashed ) {
            $cond->whereNull( $col( 'variant_deleted_at' ) );
        }

        // without bindings, the condition can be removed without updating the bindings of the query
        if( !empty( $cond->getBindings() ) ) {
            throw new \LogicException( 'The page variant condition must not use bindings' );
        }

        if( !empty( $cond->wheres ) ) {
            $this->wheres[] = ['type' => 'Nested', 'query' => $cond, 'boolean' => 'and', 'variant' => true];
        }

        return $this;
    }


    /**
     * The page view is read-only, pages are written by the Resource class.
     *
     * @param mixed $id
     * @return int
     */
    public function delete( $id = null )
    {
        return $this->viewed() ? $this->readOnly() : parent::delete( $id );
    }


    /**
     * The page view is read-only, pages are written by the Resource class.
     *
     * @param array<int|string, mixed> $values
     * @return bool
     */
    public function insert( array $values )
    {
        return $this->viewed() ? $this->readOnly() : parent::insert( $values );
    }


    /**
     * The page view is read-only, pages are written by the Resource class.
     *
     * @param array<string, mixed> $values
     * @param string|null $sequence
     * @return int
     */
    public function insertGetId( array $values, $sequence = null )
    {
        return $this->viewed() ? $this->readOnly() : parent::insertGetId( $values, $sequence );
    }


    /**
     * The page view is read-only, pages are written by the Resource class.
     *
     * @param array<string, mixed> $values
     * @return int
     */
    public function update( array $values )
    {
        return $this->viewed() ? $this->readOnly() : parent::update( $values );
    }


    /**
     * Returns the quoted value as SQL expression.
     *
     * @param string|null $value Language code or variant ID
     * @return Expression<float|int|literal-string> Quoted value
     */
    protected function literal( ?string $value ) : Expression
    {
        $conn = $this->getConnection();

        if( !$conn instanceof \Illuminate\Database\Connection ) {
            throw new \LogicException( 'The page view requires a database connection' );
        }

        // @phpstan-ignore argument.type (the value is quoted and escaped by the connection)
        return new Expression( $conn->escape( (string) $value ) );
    }


    /**
     * Rejects writes to the page view.
     *
     * @throws \LogicException Always
     */
    protected function readOnly() : never
    {
        throw new \LogicException( Page::READONLY );
    }


    /**
     * Tests if the builder reads from the page view.
     */
    protected function viewed() : bool
    {
        return is_string( $this->from ) && ( $this->from === self::VIEW || str_starts_with( $this->from, self::VIEW . ' ' ) );
    }


    /**
     * Removes the variant condition from the where clauses.
     *
     * Eloquent scopes may group the existing where clauses, so nested clauses are checked too.
     *
     * @param array<int, array<string, mixed>> $wheres Where clauses
     * @return array<int, array<string, mixed>> Where clauses without the variant condition
     */
    protected static function withoutVariant( array $wheres ) : array
    {
        $result = [];

        foreach( $wheres as $where )
        {
            if( !empty( $where['variant'] ) ) {
                continue;
            }

            if( ( $where['type'] ?? null ) === 'Nested' && ( $where['query'] ?? null ) instanceof Builder )
            {
                // nested queries are shared by cloned builders
                $query = clone $where['query'];
                $query->wheres = self::withoutVariant( $query->wheres );

                if( empty( $query->wheres ) ) {
                    continue;
                }

                $where['query'] = $query;
            }

            $result[] = $where;
        }

        return $result;
    }
}
