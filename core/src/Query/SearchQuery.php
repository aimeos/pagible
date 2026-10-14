<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\DB;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


/**
 * Translates Scout search builders to the Eloquent queries of the CMS models.
 */
class SearchQuery
{
    /**
     * Builder fields handled out-of-band; never translated to SQL columns.
     */
    private const SKIP_FIELDS = ['latest', '__soft_deleted', 'tenant_id', 'langs', 'langs_trashed'];

    /** @var \WeakMap<Builder<Model>, array{0: string, 1: bool, 2: bool}>|null Language fallbacks of the page searches */
    private static ?\WeakMap $fallbacks = null;

    /** @var \WeakMap<Builder<Model>, list<string>>|null Columns loaded by the searches */
    private static ?\WeakMap $columns = null;



    /**
     * Apply draft-mode filters for the collection engine via callback.
     *
     * @param EloquentBuilder<Model> $query
     * @param Builder<Model> $builder
     * @param array<string> $fields The fields passed to searchFields(); only 'draft' triggers this path
     * @return Builder<Model>
     */
    public static function collection( EloquentBuilder $query, Builder $builder, array $fields ) : Builder
    {
        $isDraft = in_array( 'draft', $fields );

        static::variants( $query, $builder );
        static::apply( $query, $builder, $isDraft );

        if( $builder->query === '' && $builder->queryCallback ) {
            call_user_func( $builder->queryCallback, $query );
        }

        return $builder;
    }


    /**
     * Apply Scout builder where/whereIn/whereNotIn filters and order qualification
     * to an Eloquent query, joining cms_versions when any referenced column lives
     * on the version table.
     *
     * @param EloquentBuilder<Model> $query
     * @param Builder<Model> $builder
     */
    public static function apply( EloquentBuilder $query, Builder $builder, bool $isDraft ) : void
    {
        $table = $query->getModel()->getTable();
        $driver = $query->getModel()->getConnection()->getDriverName();
        $joined = false;

        $join = function() use ( $query, $table, &$joined ) {
            if( $joined ) {
                return;
            }
            $query->select( "{$table}.*" )
                ->join( 'cms_versions', "{$table}.latest_id", '=', 'cms_versions.id' )
                ->where( 'cms_versions.tenant_id', Tenancy::value() );
            $joined = true;
        };

        $qualify = function( string $field ) use ( $driver, $isDraft, $join, $table ) : ?string {
            $col = DB::qualify( $field, $table, $isDraft, $driver );

            if( $col && $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                $join();
            }

            return $col;
        };

        self::filter( $query, $builder, $qualify );
        self::order( $builder, $qualify, $table );
    }


    /**
     * Applies the where/whereIn/whereNotIn filters of the Scout builder to the Eloquent query.
     *
     * @param EloquentBuilder<Model> $query
     * @param Builder<Model> $builder
     * @param \Closure(string): ?string $qualify Returns the qualified column of a field or NULL if unknown
     */
    private static function filter( EloquentBuilder $query, Builder $builder, \Closure $qualify ) : void
    {
        foreach( $builder->wheres as $key => $where )
        {
            $field = is_array( $where ) ? ( $where['field'] ?? $key ) : $key;

            if( in_array( $field, self::SKIP_FIELDS ) ) {
                continue;
            }

            if( !( $col = $qualify( $field ) ) ) {
                continue;
            }

            $value = self::value( $where );
            $operator = is_array( $where ) ? ( $where['operator'] ?? '=' ) : '=';

            if( is_null( $value ) ) {
                $operator === '=' ? $query->whereNull( $col ) : $query->whereNotNull( $col );
            } else {
                $query->where( $col, $operator, $value );
            }
        }

        foreach( ['whereIn' => $builder->whereIns, 'whereNotIn' => $builder->whereNotIns] as $method => $list )
        {
            foreach( $list as $field => $values )
            {
                if( $col = $qualify( $field ) ) {
                    $query->{$method}( $col, $values );
                }
            }
        }
    }


    /**
     * Qualifies the order columns of the Scout builder in place.
     *
     * The query callback runs again for paginated results, so qualified columns are skipped.
     *
     * @param Builder<Model> $builder
     * @param \Closure(string): ?string $qualify Returns the qualified column of a field or NULL if unknown
     */
    private static function order( Builder $builder, \Closure $qualify, string $table ) : void
    {
        foreach( $builder->orders as $idx => $order )
        {
            if( !str_contains( $order['column'], '.' ) ) {
                $builder->orders[$idx]['column'] = $qualify( $order['column'] ) ?? $table . '.' . $order['column'];
            }
        }
    }


    /**
     * Applies the language fallback of the page search to the query.
     *
     * @param EloquentBuilder<covariant Model> $query
     * @param Builder<covariant Model> $builder
     * @return bool TRUE if the search uses a language fallback
     */
    public static function fallback( EloquentBuilder $query, Builder $builder ) : bool
    {
        if( !$query instanceof PageQuery || !( $entry = self::$fallbacks[$builder] ?? null ) ) {
            return false;
        }

        [$lang, $trashed, $only] = $entry;

        // external engines only contain trashed variants if soft deleted models are indexed
        $query->fallback( $lang, $trashed && ( !Scout::usesExternalSearch() || config( 'scout.soft_delete', false ) ) );

        if( $trashed ) {
            $query->withoutGlobalScope( SoftDeletingScope::class );
        }

        if( $only )
        {
            $table = $query->getModel()->getTable();
            $query->where( fn( $q ) => $q
                ->whereNotNull( $table . '.deleted_at' )
                ->orWhereNotNull( $table . '.variant_deleted_at' )
            );
        }

        return true;
    }


    /**
     * Selects the page variants of the language the search is limited to and applies the language fallback.
     *
     * @param EloquentBuilder<Model> $query
     * @param Builder<Model> $builder
     */
    public static function variants( EloquentBuilder $query, Builder $builder ) : void
    {
        // withTrashed() removes the soft delete filter, so trashed variants are included if soft deletes are indexed
        $where = collect( $builder->wheres )->firstWhere( 'field', '__soft_deleted' );
        $trashed = $where ? $where['value'] === 1 : (bool) config( 'scout.soft_delete', false );

        if( $query instanceof PageQuery && ( $lang = static::language( $builder ) ) !== null ) {
            $query->language( $lang, $trashed );
        }

        static::fallback( $query, $builder );
    }


    /**
     * Lists the pages in the given language or in their source language if they have no variant in that language.
     *
     * Use "with" for the trashed filter of the search, the trashed variants are
     * handled by the fallback.
     *
     * @param Builder<Model> $builder Page search
     * @param string $lang Language code
     * @param string|null $trashed NULL or "without" for available pages and variants only, "with" to include trashed ones, "only" for trashed ones
     * @return Builder<Model> Page search with the language fallback
     * @throws \InvalidArgumentException If the language code is invalid
     */
    public static function prefer( Builder $builder, string $lang, ?string $trashed = null ) : Builder
    {
        // search engines like Meilisearch don't escape filter values
        if( !Utils::isValidLang( $lang ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        $with = in_array( $trashed, ['with', 'only'], true );

        self::$fallbacks ??= new \WeakMap();
        self::$fallbacks[$builder] = [$lang, $with, $trashed === 'only'];

        // the index contains the languages each variant is listed for, so the engine returns one variant per page
        if( Scout::usesExternalSearch() ) {
            $builder->where( $with && config( 'scout.soft_delete', false ) ? 'langs_trashed' : 'langs', $lang );
        }

        return $builder;
    }


    /**
     * Limits the columns of the models loaded for the search results.
     *
     * A query callback would do the same but makes Scout fetch the keys of all results again
     * to count them, so the columns are kept separately and applied when the models are loaded.
     *
     * @param Builder<Model> $builder Search builder
     * @param list<string> $columns Unqualified column names
     * @return Builder<Model> Same search builder
     */
    public static function select( Builder $builder, array $columns ) : Builder
    {
        self::$columns ??= new \WeakMap();
        self::$columns[$builder] = $columns;

        return $builder;
    }


    /**
     * Selects the columns set for the search in the query loading the models.
     *
     * @param EloquentBuilder<covariant Model> $query
     * @param Builder<covariant Model> $builder
     */
    public static function columns( EloquentBuilder $query, Builder $builder ) : void
    {
        if( $columns = self::$columns[$builder] ?? null ) {
            $query->select( array_map( fn( $col ) => $query->qualifyColumn( $col ), $columns ) );
        }
    }


    /**
     * Returns the language the search is limited to.
     *
     * @param Builder<Model> $builder
     * @return string|null Language code or NULL if not filtered by one language
     */
    public static function language( Builder $builder ) : ?string
    {
        foreach( $builder->wheres as $key => $where )
        {
            if( ( $where['field'] ?? $key ) !== 'lang' ) {
                continue;
            }

            $value = self::value( $where );

            if( ( $where['operator'] ?? '=' ) === '=' && is_scalar( $value ) && (string) $value !== '' ) {
                return (string) $value;
            }
        }

        return null;
    }


    /**
     * Returns the value of a Scout where clause.
     *
     * @param mixed $where Plain value or array with "value" key
     */
    private static function value( mixed $where ) : mixed
    {
        return is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;
    }
}
