<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;
use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;


/**
 * Base query builder for the page view
 *
 * Pages are stored in two tables: cms_pages contains the tree structure and
 * cms_page_variants one row per page and language. The builder reads from the
 * cms_page_view view joining both tables and a condition added by the Variant
 * scope selects the variant of each page. Language fallbacks replace the view
 * by a derived table of the same name. The view is read-only, the Page
 * model writes the tree columns to cms_pages and the PageVariant model the
 * language specific ones to cms_page_variants.
 */
class PageBuilder extends Builder
{
    /** @var string Name of the view joining the pages and their variants */
    public const VIEW = 'cms_page_view';

    /** @var int Maximum number of rows read by queries with the SQL Server join hint */
    private const HINT_ROWS = 1000;

    /** @var array<string, string> Page view columns of the variant columns which are also stored in cms_pages, and their real names */
    private const ALIAS_FIELDS = ['variant_id' => 'id', 'variant_deleted_at' => 'deleted_at'];

    /** @var list<string> Columns stored in both tables, the page view contains the ones of the variants */
    public const SHARED_FIELDS = ['created_at', 'updated_at'];

    /** @var array{0: string, 1: string|null, 2: bool}|null Mode, value and trashed flag of the variant condition */
    private ?array $variant = null;

    /** @var bool TRUE if the page view is replaced by the derived table of a language fallback */
    private bool $derived = false;

    /** @var bool TRUE while a paginated query of the language fallback is executed on SQL Server */
    private bool $hint = false;


    /**
     * Returns the values of the page view columns stored in the cms_pages table.
     *
     * @param array<string, mixed> $values Column/value pairs of the page view
     * @return array<string, mixed> Column/value pairs of the page
     */
    public static function pageValues( array $values ) : array
    {
        return array_intersect_key( $values, array_flip( [...Page::FIELDS, ...self::SHARED_FIELDS] ) );
    }


    /**
     * Returns the values of the page view columns stored in the cms_page_variants table by their variant column names.
     *
     * @param array<string, mixed> $values Column/value pairs of the page view
     * @param bool $shared TRUE to include the columns stored in both tables, e.g. the timestamps
     * @return array<string, mixed> Column/value pairs of the variant
     */
    public static function variantValues( array $values, bool $shared = false ) : array
    {
        $result = [];

        foreach( self::variantFields() as $col => $name )
        {
            if( array_key_exists( $col, $values ) ) {
                $result[$name] = $values[$col];
            }
        }

        return $shared ? $result + array_intersect_key( $values, array_flip( self::SHARED_FIELDS ) ) : $result;
    }


    /**
     * Returns the values stored in the cms_page_variants table by their page view column names.
     *
     * @param array<string, mixed> $values Column/value pairs of the variant
     * @return array<string, mixed> Column/value pairs of the page view including the columns stored in both tables
     */
    public static function viewValues( array $values ) : array
    {
        $result = [];

        foreach( self::variantFields() as $col => $name )
        {
            if( array_key_exists( $name, $values ) ) {
                $result[$col] = $values[$name];
            }
        }

        return $result + array_intersect_key( $values, array_flip( self::SHARED_FIELDS ) );
    }


    /**
     * Returns the page view columns stored in the cms_page_variants table besides the timestamps.
     *
     * @return array<string, string> Page view column names as keys and the variant column names as values
     */
    public static function variantFields() : array
    {
        return array_combine( PageVariant::FIELDS, PageVariant::FIELDS ) + self::ALIAS_FIELDS;
    }


    /**
     * Set the table which the query is targeting.
     *
     * The variant condition only applies to the page view, so it's removed if another table
     * is used or the page view is aliased. The nested set builds subqueries on the page view, e.g. for
     * has('ancestors') or whereAncestorOf(), and the condition would refer to the row of the outer query.
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

        $this->derived = false;

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
    public function variants( string $mode, ?string $value = null, bool $trashed = false ) : static
    {
        if( !in_array( $mode, ['source', 'lang', 'fallback', 'visible', 'variant', 'all'], true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid page variant mode "%1$s"', $mode ) );
        }

        $this->from( self::VIEW );
        $this->variant = [$mode, $value, $trashed];

        return $this;
    }


    /**
     * Returns the variant condition of the query.
     *
     * @return array{0: string, 1: string|null, 2: bool}|null Mode, value and trashed flag or NULL if no variant is selected
     */
    public function variant() : ?array
    {
        return $this->variant;
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

        if( $this->derived )
        {
            $this->from = self::VIEW;
            $this->derived = false;
        }

        [$mode, $value, $trashed] = $this->variant;
        $col = fn( string $name ) => self::VIEW . '.' . $name;
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
                $trashed = $trashed && $mode !== 'visible';
                $this->fromFallback( (string) $value, $mode === 'visible', $trashed );
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
     * Tests if the rows can be counted without joining the variants of the language fallback.
     *
     * Paginators can fetch one more row instead of counting all rows otherwise (see runPaginationCountQuery()).
     *
     * @return bool TRUE if counting is cheap, FALSE if it requires the joins of the language fallback
     */
    public function countable() : bool
    {
        return !$this->derived || ( !$this->groups && !$this->havings && !$this->unions && !$this->joins
            && !$this->distinct && self::pageColumns( $this->wheres ) );
    }


    /**
     * The page view is read-only, write pages using the Page and PageVariant models.
     *
     * @param mixed $id
     * @return int
     */
    public function delete( $id = null )
    {
        return $this->viewed() ? $this->readOnly() : parent::delete( $id );
    }


    /**
     * The page view is read-only, write pages using the Page and PageVariant models.
     *
     * @param array<int|string, mixed> $values
     * @return bool
     */
    public function insert( array $values )
    {
        return $this->viewed() ? $this->readOnly() : parent::insert( $values );
    }


    /**
     * The page view is read-only, write pages using the Page and PageVariant models.
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
     * Returns the SQL of the query including the query hints added by runSelect().
     *
     * @return string SQL statement
     */
    public function toSql()
    {
        $sql = parent::toSql();
        return $this->hint ? $sql . ' option (fast ' . ( (int) $this->offset + (int) $this->limit ) . ', loop join)' : $sql;
    }


    /**
     * The page view is read-only, write pages using the Page and PageVariant models.
     *
     * @param array<string, mixed> $values
     * @return int
     */
    public function update( array $values )
    {
        return $this->viewed() ? $this->readOnly() : parent::update( $values );
    }


    /**
     * Uses the variant of the first language of the fallback chain or the source variant if there is none.
     *
     * The page view is replaced by a derived table of the same name which joins the variants of the
     * chain languages by the unique page/language index, so the columns used by the query stay
     * unambiguous and the databases merge the derived table into the query.
     *
     * The databases only merge the derived table as long as it contains plain joins and conditions.
     * Adding DISTINCT, GROUP BY, aggregates, LIMIT or UNION to it makes MySQL and MariaDB materialize
     * the joins for all pages before the conditions of the outer query are applied.
     *
     * @param string $lang Language code
     * @param bool $visible TRUE to use published and enabled variants of the languages only
     * @param bool $trashed TRUE to use trashed variants of the languages too
     */
    protected function fromFallback( string $lang, bool $visible, bool $trashed ) : void
    {
        $grammar = $this->getGrammar();
        $query = $this->newQuery()->select( 'pv.*' )->from( self::VIEW . ' as pv' );
        $cols = [];

        foreach( Utils::chain( $lang ) as $idx => $code )
        {
            $alias = 'f' . $idx;
            $cols[] = $grammar->wrap( $alias . '.lang' );

            $query->leftJoin( 'cms_page_variants as ' . $alias, function( $join ) use ( $alias, $code, $visible, $trashed ) {
                $join->on( $alias . '.page_id', '=', 'pv.id' )->on( $alias . '.tenant_id', '=', 'pv.tenant_id' );
                $join->where( $alias . '.lang', '=', $this->literal( $code ) )
                    // for "visible" only published and enabled variants
                    ->when( $visible, fn( $q ) => $q->where( $alias . '.status', '<>', new Expression( '0' ) ) )
                    ->when( !$trashed, fn( $q ) => $q->whereNull( $alias . '.deleted_at' ) );
            } );
        }

        // invalid language codes have no chain and use the source variants
        $cols
            ? $query->where( 'pv.lang', '=', new Literal( 'COALESCE(' . implode( ', ', $cols ) . ', ' . $grammar->wrap( 'pv.source' ) . ')' ) )
            : $query->whereColumn( 'pv.lang', '=', 'pv.source' );

        if( !empty( $query->getBindings() ) ) {
            throw new \LogicException( 'The page variant condition must not use bindings' );
        }

        parent::fromSub( $query, self::VIEW );
        $this->derived = true;
    }


    /**
     * Returns the quoted value as SQL expression.
     *
     * @param string|null $value Language code or variant ID
     * @return Literal Quoted value
     */
    protected function literal( ?string $value ) : Literal
    {
        $conn = $this->getConnection();

        if( !$conn instanceof Connection ) {
            throw new \LogicException( 'The page view requires a database connection' );
        }

        return new Literal( $conn->escape( (string) $value ) );
    }


    /**
     * Rejects writes to the page view.
     *
     * @throws \LogicException Always
     */
    protected function readOnly() : never
    {
        throw new \LogicException( PageView::READONLY );
    }


    /**
     * Counts the rows for the paginator, counting the pages of the language fallback without joining the chain variants.
     *
     * The source variant of a page can't be trashed (see Variants::drop() and Variants::setSource()),
     * so each page has exactly one row in the derived table of the fallback. If the query only filters
     * by page columns, the pages are counted instead of joining the variants of all chain languages.
     *
     * @param array<int, string|\Illuminate\Contracts\Database\Query\Expression> $columns Columns
     * @return array<int, mixed> Rows with the count
     */
    protected function runPaginationCountQuery( $columns = ['*'] )
    {
        if( $columns === ['*'] && $this->derived && $this->countable() )
        {
            // the page columns of the view keep their names if the pages table is aliased by the view name
            return $this->cloneWithout( ['columns', 'orders', 'limit', 'offset'] )->cloneWithoutBindings( ['select', 'order'] )
                ->from( 'cms_pages', self::VIEW )->runPaginationCountQuery( $columns );
        }

        return parent::runPaginationCountQuery( $columns );
    }


    /**
     * Runs the query, using nested loops for pages of the language fallback on SQL Server.
     *
     * SQL Server joins the variants of all pages by hash and sorts them afterwards to return
     * one page of a list, which is several times slower than reading only the rows of the
     * page by nested loops. The hint is added to executed queries only because subqueries
     * can't contain query hints. Nested loops are slower for many rows and the hint applies to all
     * joins of the query, so it's only used for the first rows of queries without additional joins.
     *
     * @return array<int, mixed> Rows of the result
     */
    protected function runSelect()
    {
        $this->hint = $this->derived && $this->limit && !$this->unions && !$this->joins
            && (int) $this->offset + (int) $this->limit <= self::HINT_ROWS && $this->grammar instanceof SqlServerGrammar;

        try {
            return parent::runSelect();
        } finally {
            $this->hint = false;
        }
    }


    /**
     * Tests if the builder reads from the page view.
     */
    protected function viewed() : bool
    {
        return $this->derived || is_string( $this->from ) && ( $this->from === self::VIEW || str_starts_with( $this->from, self::VIEW . ' ' ) );
    }


    /**
     * Tests if the where clauses only use page columns stored in the cms_pages table.
     *
     * The variant condition is skipped, unknown clause types and expressions are treated as variant columns.
     *
     * @param array<int, array<string, mixed>> $wheres Where clauses
     * @return bool TRUE if only page columns are used
     */
    protected static function pageColumns( array $wheres ) : bool
    {
        $page = fn( mixed $col ) => is_string( $col )
            && in_array( str_starts_with( $col, self::VIEW . '.' ) ? substr( $col, strlen( self::VIEW ) + 1 ) : $col, Page::FIELDS, true );

        foreach( $wheres as $where )
        {
            if( !empty( $where['variant'] ) ) {
                continue;
            }

            $ok = match( $where['type'] ?? null ) {
                'Basic' => $page( $where['column'] ) && !$where['value'] instanceof ExpressionContract,
                'In', 'NotIn', 'InRaw', 'NotInRaw', 'Null', 'NotNull', 'between' => $page( $where['column'] ),
                'Column' => $page( $where['first'] ) && $page( $where['second'] ),
                'Nested' => ( $where['query'] ?? null ) instanceof Builder && self::pageColumns( $where['query']->wheres ),
                default => false,
            };

            if( !$ok ) {
                return false;
            }
        }

        return true;
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
