<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL;

use Aimeos\Cms\Query\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Query\SearchQuery;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Translation\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;


/**
 * Custom query resolvers for paginated list queries.
 */
final class Query
{
    /**
     * Resolver for paginated element list query.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return LengthAwarePaginator<int, Element>
     */
    public function elements( $rootValue, array $args ) : LengthAwarePaginator
    {
        $filter = $args['filter'] ?? [];

        $search = Filter::search( Element::class, $filter['any'] ?? '' );

        Filter::elements( $search, $filter + $args );

        $allowed = ['id', 'latest_id', 'lang', 'name', 'type', 'editor'];
        return $this->paginate( $search, $args, $allowed, 'id', 'desc' );
    }


    /**
     * Resolver for paginated file list query.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @param GraphQLContext|null $context GraphQL request context
     * @param ResolveInfo|null $resolveInfo Requested fields
     * @return LengthAwarePaginator<int, File>
     */
    public function files( $rootValue, array $args, ?GraphQLContext $context = null,
        ?ResolveInfo $resolveInfo = null ) : LengthAwarePaginator
    {
        $filter = $args['filter'] ?? [];
        $available = ['disk', 'lang', 'name', 'mime', 'path', 'previews', 'description', 'transcription', 'editor',
            'created_at', 'updated_at', 'deleted_at'];
        $fields = $resolveInfo
            ? (array) ( $resolveInfo->getFieldSelection( 1 )['data'] ?? [] )
            : array_fill_keys( [...$available, 'latest', 'byversions_count'], true );
        $columns = array_map( fn( $column ) => 'cms_files.' . $column, array_intersect( $available, array_keys( $fields ) ) );
        $columns[] = 'cms_files.id';

        if( isset( $fields['latest'] ) ) {
            $columns[] = 'cms_files.latest_id';
        }

        $search = Filter::search( File::class, $filter['any'] ?? '' );

        $search->query( function( $query ) use ( $args, $columns, $fields ) {
            $query->select( array_values( array_unique( $columns ) ) );

            if( isset( $fields['byversions_count'] ) || in_array( 'byversions_count', array_column( $args['sort'] ?? [], 'column' ), true ) ) {
                $query->withCount( 'byversions' );
            }
        } );

        Filter::files( $search, $filter + $args );

        $allowed = ['id', 'latest_id', 'name', 'mime', 'lang', 'editor', 'byversions_count'];
        return $this->paginate( $search, $args, $allowed, 'id', 'desc' );
    }


    /**
     * Resolver for a single page in the source language or the requested language.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function page( $rootValue, array $args ) : ?PageView
    {
        $trashed = $args['trashed'] ?? null;

        $query = match( $trashed ) {
            'with' => PageView::withTrashed(),
            'only' => PageView::onlyTrashed(),
            default => PageView::query(),
        };

        if( isset( $args['lang'] ) ) {
            $query->language( (string) $args['lang'], in_array( $trashed, ['with', 'only'], true ) );
        }

        return $query->whereKey( $args['id'] )->first();
    }


    /**
     * Resolver for the number of pages in each translation state of the languages.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, array{lang: string, stale: int, missing: int, ai: int}>
     */
    public function translationStates( $rootValue, array $args ) : array
    {
        $langs = array_map( fn( $lang ) => trim( (string) $lang ), (array) ( $args['langs'] ?? config( 'cms.locales', [] ) ) );
        $langs = array_values( array_unique( array_filter( $langs ) ) );

        return $langs ? $this->states( $langs, !empty( $args['cached'] ) ) : [];
    }


    /**
     * Counts the pages in each translation state of the languages.
     *
     * The counts scan all variants of the languages, so frequent refreshes can use
     * counts up to a minute old. Fresh counts replace the cached ones but are computed
     * at most ten times a minute per tenant and user, otherwise the cached counts are used.
     *
     * @param  array<int, string>  $langs Language codes
     * @param  bool  $cached TRUE to use counts up to a minute old if available
     * @return array<int, array{lang: string, stale: int, missing: int, ai: int}>
     */
    protected function states( array $langs, bool $cached = false ) : array
    {
        $sorted = $langs;
        sort( $sorted );

        // hashed because tenant IDs and language lists may exceed the key length of cache stores like memcached
        $key = 'cms-translation-states:' . sha1( Tenancy::value() . ':' . implode( ',', $sorted ) );
        $counts = Cache::get( $key );

        if( !is_array( $counts ) || !$cached && RateLimiter::attempt( 'cms-translation-states:' . sha1( Tenancy::value() . ':' . Auth::id() ), 10, fn() => true ) ) {
            Cache::put( $key, $counts = $this->counts( $langs ), 60 );
        }

        return array_map( function( string $lang ) use ( $counts ) {
            $row = $counts['rows'][$lang] ?? [];

            return [
                'lang' => $lang,
                'stale' => (int) ( $row['stale'] ?? 0 ),
                'missing' => max( 0, (int) ( $counts['pages'] ?? 0 ) - (int) ( $row['total'] ?? 0 ) ),
                'ai' => (int) ( $row['ai'] ?? 0 ),
            ];
        }, $langs );
    }


    /**
     * Counts the variants in each translation state of the languages and all pages.
     *
     * @param  array<int, string>  $langs Language codes
     * @return array{rows: array<string, array{total: int, stale: int, ai: int}>, pages: int}
     */
    protected function counts( array $langs ) : array
    {
        // same conditions as Filter::translation() but counted on the variants of the languages
        // using their indexes instead of the fallback join over all pages
        $rows = PageVariant::query()
            ->join( 'cms_pages as p', fn( $join ) => $join
                ->on( 'p.id', '=', 'cms_page_variants.page_id' )
                ->on( 'p.tenant_id', '=', 'cms_page_variants.tenant_id' )
            )
            // joined instead of an EXISTS subquery because SQL Server can't aggregate subqueries,
            // the latest version is unique so the variants are still counted once
            ->leftJoin( 'cms_versions as v', fn( $join ) => $join
                ->on( 'v.id', '=', 'cms_page_variants.latest_id' )
                ->where( 'v.editor', '=', Sync::EDITOR )
            )
            ->whereNull( 'p.deleted_at' )
            ->whereIn( 'cms_page_variants.lang', $langs )
            ->toBase()
            ->select( 'cms_page_variants.lang' )
            ->selectRaw( '
                COUNT(*) AS total,
                SUM(CASE WHEN cms_page_variants.stale = ? THEN 1 ELSE 0 END) AS stale,
                SUM(CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END) AS ai
            ', [true] )
            ->groupBy( 'cms_page_variants.lang' )
            ->get()
            ->mapWithKeys( fn( $row ) => [(string) $row->lang => [
                'total' => (int) $row->total,
                'stale' => (int) $row->stale,
                'ai' => (int) $row->ai,
            ]] )
            ->all();

        // pages without a variant in the language
        $pages = PageView::query()->getConnection()->table( 'cms_pages' )
            ->where( 'tenant_id', Tenancy::value() )
            ->whereNull( 'deleted_at' )
            ->count();

        return ['rows' => $rows, 'pages' => $pages];
    }


    /**
     * Resolver for paginated page list query.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @param GraphQLContext|null $context GraphQL request context
     * @param ResolveInfo|null $resolveInfo Requested fields
     * @return LengthAwarePaginator<int, PageView>
     */
    public function pages( $rootValue, array $args, ?GraphQLContext $context = null,
        ?ResolveInfo $resolveInfo = null ) : LengthAwarePaginator
    {
        $search = $this->search( $args );

        // lists don't load the content, meta and config of each page if they aren't requested
        if( $resolveInfo )
        {
            $fields = array_keys( (array) ( $resolveInfo->getFieldSelection( 1 )['data'] ?? [] ) );
            $columns = [...PageView::REQUIRED_COLUMNS, 'variant_deleted_at', ...array_intersect( PageView::RESPONSE_COLUMNS, $fields )];

            SearchQuery::select( $search, array_values( array_unique( $columns ) ) );
        }

        $allowed = ['id', 'latest_id', 'name', 'title', 'editor', NestedSet::LFT];
        return $this->paginate( $search, $args, $allowed, NestedSet::LFT, 'asc' );
    }


    /**
     * Returns the search builder for the pages matching the arguments of the page list query.
     *
     * @param  array<string, mixed>  $args Arguments of the page list query ("filter", "lang", "publish", "trashed")
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> Unsorted search builder
     */
    private function search( array $args ) : \Laravel\Scout\Builder
    {
        $filter = $args['filter'] ?? [];
        $route = array_key_exists( 'path', $filter )
            ? array_intersect_key( $filter, array_flip( ['path', 'domain'] ) )
            : [];

        $search = Filter::search( PageView::class, $filter['any'] ?? '' );
        $lang = (string) ( $args['lang'] ?? '' );
        $trashed = $args['trashed'] ?? null;
        $state = null;

        if( isset( $args['lang'] ) )
        {
            // trashed variants of the language are selected by the fallback
            $state = $filter['translation'] ?? null;
            SearchQuery::prefer( $search, $lang, $trashed );
            $args['trashed'] = $trashed === 'only' ? 'with' : $trashed;
            unset( $filter['lang'], $args['lang'] );
        }

        unset( $filter['translation'] );

        // external engines only apply query callbacks to the fetched results, so the indexed fields are used
        if( $state && Scout::usesExternalSearch() )
        {
            match( $state ) {
                'missing' => $search->whereNotIn( 'lang', [$lang] ),
                'stale' => $search->where( 'lang', $lang )->where( 'stale', true ),
                'ai' => $search->where( 'lang', $lang )->where( 'editor', Sync::EDITOR ),
                default => throw new \InvalidArgumentException( sprintf( 'Invalid translation state "%1$s"', $state ) ),
            };
            $state = null;
        }

        Filter::pages( $search, array_diff_key( $filter, $route ) + $args );

        if( $route || $state ) {
            $search->query( function( \Illuminate\Database\Eloquent\Builder $query ) use ( $route, $state, $lang ) {
                foreach( $route as $field => $value ) {
                    $query->where( $query->qualifyColumn( $field ), (string) ( $value ?? '' ) );
                }

                if( $state ) {
                    Filter::translation( $query, $lang, $state );
                }
            } );
        }

        return $search;
    }


    /**
     * Applies the allowlisted sort clauses and returns the requested page of results.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $search
     * @param array<string, mixed> $args GraphQL arguments with "first", "page" and "sort"
     * @param array<int, string> $allowed Allowlisted column names
     * @param string $column Default sort column
     * @param string $dir Default sort direction
     * @return LengthAwarePaginator<int, mixed>
     */
    private function paginate( $search, array $args, array $allowed, string $column, string $dir ) : LengthAwarePaginator
    {
        $applied = false;

        foreach( $args['sort'] ?? [] as $clause )
        {
            if( in_array( $clause['column'], $allowed ) ) {
                $search->orderBy( $clause['column'], $clause['order'] );
                $applied = true;
            }
        }

        if( !$applied ) {
            $search->orderBy( $column, $dir );
        }

        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );

        return $search->paginate( $limit, 'page', max( (int) ( $args['page'] ?? 1 ), 1 ) );
    }
}
