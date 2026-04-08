<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;


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
     * @return LengthAwarePaginator<int, File>
     */
    public function files( $rootValue, array $args ) : LengthAwarePaginator
    {
        $filter = $args['filter'] ?? [];
        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );
        $page = max( (int) ( $args['page'] ?? 1 ), 1 );

        $search = File::search( mb_substr( trim( (string) ( $filter['any'] ?? '' ) ), 0, 200 ) )
            ->searchFields( 'draft' );

        $search->query( fn( $q ) => $q->addSelect( ['byversions_count' => DB::table( 'cms_version_file' )
            ->selectRaw( 'count(*)' )
            ->whereColumn( 'file_id', 'cms_files.id' )] ) );

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

        $allowed = ['id', 'name', 'title', 'editor', '_lft'];
        $this->sort( $search, $args['sort'] ?? [], $allowed, '_lft', 'asc' );

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
