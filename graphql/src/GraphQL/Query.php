<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
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
        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );
        $page = max( (int) ( $args['page'] ?? 1 ), 1 );

        $search = Element::search( mb_substr( trim( (string) ( $filter['any'] ?? '' ) ), 0, 200 ) )
            ->searchFields( 'draft' );

        Filter::elements( $search, $filter + $args );

        $allowed = ['id', 'lang', 'name', 'type', 'editor'];
        $this->sort( $search, $args['sort'] ?? [], $allowed, 'id', 'desc' );

        return $search->paginate( $limit, 'page', $page );
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
        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );
        $page = max( (int) ( $args['page'] ?? 1 ), 1 );
        $available = ['lang', 'name', 'mime', 'path', 'previews', 'description', 'transcription', 'editor',
            'created_at', 'updated_at', 'deleted_at'];
        $fields = $resolveInfo
            ? (array) ( $resolveInfo->getFieldSelection( 1 )['data'] ?? [] )
            : array_fill_keys( [...$available, 'latest', 'byversions_count'], true );
        $columns = array_map( fn( $column ) => 'cms_files.' . $column, array_intersect( $available, array_keys( $fields ) ) );
        $columns[] = 'cms_files.id';

        if( isset( $fields['latest'] ) ) {
            $columns[] = 'cms_files.latest_id';
        }

        $search = File::search( mb_substr( trim( (string) ( $filter['any'] ?? '' ) ), 0, 200 ) )
            ->searchFields( 'draft' );

        $search->query( function( $query ) use ( $args, $columns, $fields ) {
            $query->select( array_values( array_unique( $columns ) ) );

            if( isset( $fields['byversions_count'] ) || in_array( 'byversions_count', array_column( $args['sort'] ?? [], 'column' ), true ) ) {
                $query->withCount( 'byversions' );
            }
        } );

        Filter::files( $search, $filter + $args );

        $allowed = ['id', 'name', 'mime', 'lang', 'editor', 'byversions_count'];
        $this->sort( $search, $args['sort'] ?? [], $allowed, 'id', 'desc' );

        return $search->paginate( $limit, 'page', $page );
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
        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );
        $page = max( (int) ( $args['page'] ?? 1 ), 1 );

        $search = Page::search( mb_substr( trim( (string) ( $filter['any'] ?? '' ) ), 0, 200 ) )
            ->searchFields( 'draft' );

        Filter::pages( $search, $filter + $args );

        $allowed = ['id', 'name', 'title', 'editor', NestedSet::LFT];
        $this->sort( $search, $args['sort'] ?? [], $allowed, NestedSet::LFT, 'asc' );

        return $search->paginate( $limit, 'page', $page );
    }


    /**
     * Apply sort clauses from @orderBy to the Scout builder.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $search
     * @param array<int, array{column: string, order: string}> $clauses
     * @param array<int, string> $allowed Allowlisted column names
     * @param string $defaultColumn Default sort column
     * @param string $defaultDirection Default sort direction
     */
    private function sort( $search, array $clauses, array $allowed, string $defaultColumn, string $defaultDirection ) : void
    {
        $applied = false;

        foreach( $clauses as $clause )
        {
            if( in_array( $clause['column'], $allowed ) ) {
                $search->orderBy( $clause['column'], $clause['order'] );
                $applied = true;
            }
        }

        if( !$applied ) {
            $search->orderBy( $defaultColumn, $defaultDirection );
        }
    }
}
