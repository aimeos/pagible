<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\IndexModels;
use Aimeos\Cms\Query\PageQuery;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Laravel\Scout\ModelObserver;
use Laravel\Scout\Builder;


/**
 * Scout search builder support.
 */
class Scout
{
    /**
     * Builder fields handled out-of-band; never translated to SQL columns.
     */
    public const SKIP_FIELDS = ['latest', '__soft_deleted', 'tenant_id', 'langs', 'langs_trashed'];

    /** @var \WeakMap<Builder<\Illuminate\Database\Eloquent\Model>, array{0: string, 1: bool, 2: bool}>|null Language fallbacks of the page searches */
    private static ?\WeakMap $fallbacks = null;

    /** @var array<string, array<string, bool>> Page IDs per tenant whose source variants are reindexed after commit */
    private static array $sources = [];


    /**
     * Apply draft-mode filters for the collection engine via callback.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string> $fields The fields passed to searchFields(); only 'draft' triggers this path
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function collection( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, array $fields ) : Builder
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
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     */
    public static function apply( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, bool $isDraft ) : void
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

        foreach( $builder->wheres as $key => $where )
        {
            $field = is_array( $where ) ? ( $where['field'] ?? $key ) : $key;

            if( in_array( $field, self::SKIP_FIELDS ) ) {
                continue;
            }

            if( !( $col = $qualify( $field ) ) ) {
                continue;
            }

            $value = is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;
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

        foreach( $builder->orders as &$order ) {
            $order['column'] = $qualify( $order['column'] ) ?? $table . '.' . $order['column'];
        }
    }


    /**
     * Applies the language fallback of the page search to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<covariant \Illuminate\Database\Eloquent\Model> $builder
     * @return bool TRUE if the search uses a language fallback
     */
    public static function fallback( \Illuminate\Database\Eloquent\Builder $query, Builder $builder ) : bool
    {
        if( !$query instanceof PageQuery || !( $entry = self::$fallbacks[$builder] ?? null ) ) {
            return false;
        }

        [$lang, $trashed, $only] = $entry;

        // external engines only contain trashed variants if soft deleted models are indexed
        $query->fallback( $lang, $trashed && ( !self::usesExternalSearch() || config( 'scout.soft_delete', false ) ) );

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
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     */
    public static function variants( \Illuminate\Database\Eloquent\Builder $query, Builder $builder ) : void
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
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder Page search
     * @param string $lang Language code
     * @param string|null $trashed NULL or "without" for available pages and variants only, "with" to include trashed ones, "only" for trashed ones
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> Page search with the language fallback
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
        if( self::usesExternalSearch() ) {
            $builder->where( $with && config( 'scout.soft_delete', false ) ? 'langs_trashed' : 'langs', $lang );
        }

        return $builder;
    }


    /**
     * Returns the languages a page variant is listed for by searches with language fallback.
     *
     * Variants are listed for their own language and source variants also for the languages
     * from "cms.locales" the page has no variant for.
     *
     * @param Models\Page $page Page variant
     * @param bool $trashed TRUE if trashed variants are listed too
     * @return array<int, string> Language codes
     */
    public static function langs( Models\Page $page, bool $trashed ) : array
    {
        $lang = (string) $page->lang;

        if( !$trashed && $page->getAttribute( 'variant_deleted_at' ) !== null ) {
            return [];
        }

        if( $lang !== (string) $page->source ) {
            return [$lang];
        }

        $variants = $page->languages;
        $existing = ( $trashed ? $variants : $variants->whereNull( 'deleted_at' ) )->pluck( 'lang' )->all();
        $locales = array_map( strval( ... ), (array) config( 'cms.locales', [] ) );

        return array_values( array_unique( [$lang, ...array_diff( $locales, $existing )] ) );
    }


    /**
     * Returns the language the search is limited to.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @return string|null Language code or NULL if not filtered by one language
     */
    public static function language( Builder $builder ) : ?string
    {
        foreach( $builder->wheres as $key => $where )
        {
            if( ( $where['field'] ?? $key ) !== 'lang' ) {
                continue;
            }

            $value = is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;

            if( ( $where['operator'] ?? '=' ) === '=' && is_scalar( $value ) && (string) $value !== '' ) {
                return (string) $value;
            }
        }

        return null;
    }


    /**
     * Reindexes models by ID in bounded native Scout batches.
     *
     * All variants of the pages are reindexed unless their changed variants are passed as loaded models
     * or the search keys of the changed variants are passed instead of the page IDs.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs (page IDs for pages) or search keys (variant IDs for pages)
     * @param Collection<int, covariant Models\Base>|null $loaded Already loaded current models
     * @param bool $sources TRUE to reindex only the source variants of the pages
     * @param bool $keys TRUE if the IDs are search keys
     */
    public static function index( string $model, array $ids, ?Collection $loaded = null, bool $sources = false, bool $keys = false ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $models = [];

        // pages are indexed per variant, loaded variants are the changed ones of their page
        foreach( $loaded ?? [] as $item ) {
            if( $item instanceof $model && $item->id !== null && $item->shouldBeSearchable() ) {
                $models[$keys ? (string) $item->getScoutKey() : $item->id][$item->getScoutKey()] = $item;
            }
        }

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            if( config( 'scout.queue' ) ) {
                dispatch( ( new IndexModels( $model, $chunk, Tenancy::value(), $sources, $keys ) )
                    ->onQueue( $instance->syncWithSearchUsingQueue() )
                    ->onConnection( $instance->syncWithSearchUsing() ) );
            } elseif( !$sources && count( $items = array_intersect_key( $models, array_flip( $chunk ) ) ) === count( $chunk ) ) {
                $loaded = $instance->newCollection( array_merge( ...array_map( array_values( ... ), array_values( $items ) ) ) );
                $loaded->loadMissing( $model::makeAllSearchableQuery()->getEagerLoads() );

                // eager loads don't contain the access count, which is required for each page otherwise
                if( $instance instanceof Models\Page ) {
                    $loaded->filter( fn( $item ) => !array_key_exists( 'access_count', $item->getAttributes() ) )->loadCount( 'access' );
                }

                $instance->syncMakeSearchable( $loaded );
            } else {
                self::sync( $model, $chunk, $sources, $keys );
            }
        }
    }


    /**
     * Executes the callback without automatic Scout model synchronization.
     *
     * Already muted model classes remain muted when nested calls return.
     *
     * @template T
     * @param array<class-string<Models\Base>> $models Model classes to mute
     * @param \Closure(): T $callback Callback to execute
     * @return T Callback return value
     */
    public static function mute( array $models, \Closure $callback ) : mixed
    {
        $instances = [];

        foreach( array_unique( $models ) as $model ) {
            $instance = new $model();

            if( !ModelObserver::syncingDisabledFor( $instance ) ) {
                $instance::disableSearchSyncing();
                $instances[] = $instance;
            }
        }

        try {
            return $callback();
        } finally {
            foreach( $instances as $instance ) {
                $instance::enableSearchSyncing();
            }
        }
    }


    /**
     * Reindexes the source variants of the pages, which are listed for the languages without variant.
     *
     * Required after language variants are added or removed, only for external search engines.
     * The pages are collected and reindexed once after the surrounding transaction commits.
     *
     * @param array<string> $ids Page IDs
     */
    public static function sources( array $ids ) : void
    {
        if( !$ids || !self::usesExternalSearch() ) {
            return;
        }

        $tenant = Tenancy::value();

        foreach( $ids as $id ) {
            self::$sources[$tenant][(string) $id] = true;
        }

        // pages of rolled back transactions are reindexed with the next commit, which is harmless
        ( new Models\Page() )->getConnection()->afterCommit( function() {
            $list = self::$sources;
            self::$sources = [];

            foreach( $list as $tenant => $ids ) {
                Tenancy::run( (string) $tenant, fn() => self::index( Models\Page::class, array_map( strval( ... ), array_keys( $ids ) ), sources: true ) );
            }
        } );
    }


    /**
     * Reindexes models after the surrounding transaction commits.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs
     */
    public static function reindex( string $model, array $ids ) : void
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $tenant = Tenancy::value();

        $instance->getConnection()->afterCommit(
            fn() => Tenancy::run( $tenant, fn() => self::index( $model, $ids ) ),
        );
    }


    /**
     * Reindexes models immediately after loading their searchable relations.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs or search keys
     * @param bool $sources TRUE to reindex only the source variants of the pages
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    public static function sync( string $model, array $ids, bool $sources = false, bool $keys = false ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $key = $instance->getScoutKeyName();
        $column = $instance->qualifyColumn( $key );

        // batches by search key to limit the number of page variants for many languages,
        // larger than the ID batches to load pages with only one language in one query
        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            $query = $instance::makeAllSearchableQuery()->withoutGlobalScope( SoftDeletingScope::class );

            if( $sources && $query instanceof PageQuery ) {
                $query->language( null );
            }

            ( $keys ? $query->whereIn( $column, $chunk ) : $query->whereKey( $chunk ) )->chunkById( 100, fn( $items ) => $instance->syncMakeSearchable( $items ), $column, $key );
        }
    }


    /**
     * Returns the searchable text values from content elements.
     *
     * @param iterable<array<string, mixed>|object> $items Content elements
     * @return array<int, string> Searchable text values
     */
    public static function text( iterable $items ) : array
    {
        $schemas = Schema::schemas( section: 'content' );
        $result = [];

        foreach( $items as $item )
        {
            $item = (object) $item;
            $fields = (array) ( $schemas[$item->type ?? '']['fields'] ?? [] );

            foreach( (array) ( $item->data ?? [] ) as $name => $value )
            {
                if( is_string( $value ) && isset( $fields[$name] )
                    && ( $fields[$name]['searchable'] ?? true )
                    && in_array( $fields[$name]['type'], ['markdown', 'plaintext', 'string', 'text'], true )
                ) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }


    /**
     * Removes models from Scout by ID in bounded native batches.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Search keys (variant IDs for pages)
     */
    public static function unindex( string $model, array $ids ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $key = $instance->getScoutKeyName();

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            $items = $instance->newCollection( array_map(
                fn( $id ) => $instance->newInstance()->forceFill( [$key => $id] ),
                $chunk,
            ) );
            $instance->queueRemoveFromSearch( $items );
        }
    }


    /**
     * Whether visibility must be stored and filtered in an external search index.
     */
    public static function usesExternalSearch() : bool
    {
        return self::usesSearchIndex() && config( 'scout.driver' ) !== 'cms';
    }


    /**
     * Whether the configured Scout driver maintains a search index.
     */
    public static function usesSearchIndex() : bool
    {
        return !in_array( config( 'scout.driver' ), [null, 'null', 'collection', 'database'], true );
    }
}
