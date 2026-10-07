<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Scout;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
    public function page( $rootValue, array $args ) : ?Page
    {
        $trashed = $args['trashed'] ?? null;

        $query = match( $trashed ) {
            'with' => Page::withTrashed(),
            'only' => Page::onlyTrashed(),
            default => Page::query(),
        };

        if( isset( $args['lang'] ) ) {
            $query->language( (string) $args['lang'], in_array( $trashed, ['with', 'only'], true ) );
        }

        return $query->whereKey( $args['id'] )->first();
    }


    /**
     * Resolver for paginated page list query.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return LengthAwarePaginator<int, Page>
     */
    public function pages( $rootValue, array $args ) : LengthAwarePaginator
    {
        $filter = $args['filter'] ?? [];
        $route = array_key_exists( 'path', $filter )
            ? array_intersect_key( $filter, array_flip( ['path', 'domain'] ) )
            : [];

        $search = Filter::search( Page::class, $filter['any'] ?? '' );

        if( isset( $args['lang'] ) )
        {
            // trashed variants of the language are selected by the fallback
            Scout::prefer( $search, (string) $args['lang'], $args['trashed'] ?? null );
            $args['trashed'] = ( $args['trashed'] ?? null ) === 'only' ? 'with' : ( $args['trashed'] ?? null );
            unset( $filter['lang'], $args['lang'] );
        }

        Filter::pages( $search, array_diff_key( $filter, $route ) + $args );

        if( $route ) {
            $search->query( function( $query ) use ( $route ) {
                foreach( $route as $field => $value ) {
                    $query->where( 'cms_pages.' . $field, (string) ( $value ?? '' ) );
                }
            } );
        }

        $allowed = ['id', 'latest_id', 'name', 'title', 'editor', NestedSet::LFT];
        return $this->paginate( $search, $args, $allowed, NestedSet::LFT, 'asc' );
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
