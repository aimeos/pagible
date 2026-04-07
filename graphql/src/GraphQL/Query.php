<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL;

use Aimeos\Cms\Filter;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;


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

        $search->query( fn( $q ) => $q->withCount( 'byversions' ) );

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
     * @param  mixed  $context
     * @return LengthAwarePaginator<int, Page>
     */
    public function pages( $rootValue, array $args, mixed $context = null, ?ResolveInfo $resolveInfo = null ) : LengthAwarePaginator
    {
        $filter = $args['filter'] ?? [];
        $limit = min( max( (int) ( $args['first'] ?? 100 ), 1 ), 100 );
        $page = max( (int) ( $args['page'] ?? 1 ), 1 );

        $search = Page::search( mb_substr( trim( (string) ( $filter['any'] ?? '' ) ), 0, 200 ) )
            ->searchFields( 'draft' );

        if( $columns = $this->pageColumns( $resolveInfo ) ) {
            $search->options( ['select' => $columns] );
        }

        Filter::pages( $search, $filter + $args );

        $allowed = ['id', 'name', 'title', 'editor', '_lft'];
        $this->sort( $search, $args['sort'] ?? [], $allowed, '_lft', 'asc' );

        return $search->paginate( $limit, 'page', $page );
    }


    /**
     * Compute the minimal set of cms_pages columns needed to satisfy the
     * GraphQL field selection. Returns null when the heavy JSON columns
     * (`meta`, `content`) are requested — in that case selecting `*` is
     * fine and skipping the projection avoids unnecessary work.
     *
     * The goal is to skip the wide JSON columns (`meta`, `content`) when
     * the client doesn't ask for them, which avoids 100 random clustered
     * index lookups against UUID-keyed rows on SQL Server.
     *
     * @return list<string>|null
     */
    private function pageColumns( ?ResolveInfo $info ) : ?array
    {
        if( $info === null ) {
            return null;
        }

        $selection = $info->getFieldSelection( 2 );
        $fields = $selection['data'] ?? [];

        if( empty( $fields ) ) {
            return null;
        }

        // If the client requests the heavy JSON blobs, fall back to SELECT *.
        if( !empty( $fields['meta'] ) || !empty( $fields['content'] ) ) {
            return null;
        }

        // Always-needed bookkeeping columns (primary key, nested-set, tenancy,
        // soft delete, version pointer) regardless of field selection.
        $required = [
            'id', 'tenant_id', 'parent_id', '_lft', '_rgt', 'depth',
            'latest_id', 'deleted_at', 'created_at', 'updated_at',
        ];

        // Optional cms_pages columns loaded only when requested by the client.
        $optional = [
            'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'type',
            'theme', 'status', 'cache', 'config', 'editor', 'related_id',
        ];

        $columns = $required;

        foreach( $optional as $col ) {
            if( !empty( $fields[$col] ) ) {
                $columns[] = $col;
            }
        }

        return $columns;
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
