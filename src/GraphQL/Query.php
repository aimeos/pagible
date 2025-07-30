<?php

namespace Aimeos\Cms\GraphQL;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


/**
 * Custom query builder.
 */
final class Query
{
    /**
     * Custom query builder for elements to search items by ID (optional).
     *
     * @param  null  $rootValue
     * @param  array  $args
     * @return \Kalnoy\Nestedset\QueryBuilder
     */
    public function elements( $rootValue, array $args ) : Builder
    {
        $filter = $args['filter'] ?? [];
        $publish = $args['publish'] ?? null;
        $limit = (int) ( $args['first'] ?? 100 );

        $builder = Element::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        foreach( $args['sort'] ?? [] as $sort ) {
            $builder->orderBy( $sort['column'] ?? 'id', $sort['order'] ?? 'ASC' );
        }

        switch( $args['trashed'] ?? null ) {
            case 'without': $builder->withoutTrashed(); break;
            case 'with': $builder->withTrashed(); break;
            case 'only': $builder->onlyTrashed(); break;
        }

        $builder->whereHas('latest', function( $builder ) use ( $filter, $publish ) {

            switch( $publish )
            {
                case 'PUBLISHED': $builder->where( 'cms_versions.published', true ); break;
                case 'DRAFT': $builder->where( 'cms_versions.published', false ); break;
                case 'SCHEDULED': $builder->where( 'cms_versions.publish_at', '!=', null )
                    ->where( 'cms_versions.published', false ); break;
            }

            if( isset( $filter['id'] ) ) {
                $builder->whereIn( 'cms_versions.versionable_id', $filter['id'] );
            }

            if( isset( $filter['lang'] ) ) {
                $builder->where( 'cms_versions.lang', $filter['lang'] );
            }

            if( isset( $filter['editor'] ) ) {
                $builder->where( 'cms_versions.editor', 'like', $filter['editor'] . '%' );
            }

            if( isset( $filter['type'] ) ) {
                $builder->where( 'data->type', (string) $filter['type'] );
            }

            if( array_key_exists( 'name', $filter ) ) {
                $builder->where( 'data->name', 'like', $filter['name'] . '%' );
            }

            if( isset( $filter['any'] ) ) {
                $builder->whereAny( ['data->name', 'data->data'], 'like', '%' . $filter['any'] . '%' );
            }
        });

        return $builder;
    }


    /**
     * Custom query builder for files to search for.
     *
     * @param  null  $rootValue
     * @param  array  $args
     * @return \Kalnoy\Nestedset\QueryBuilder
     */
    public function files( $rootValue, array $args ) : Builder
    {
        $filter = $args['filter'] ?? [];
        $publish = $args['publish'] ?? null;
        $limit = (int) ( $args['first'] ?? 100 );

        $builder = File::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        foreach( $args['sort'] ?? [] as $sort ) {
            $builder->orderBy( $sort['column'] ?? 'id', $sort['order'] ?? 'ASC' );
        }

        switch( $args['trashed'] ?? null ) {
            case 'without': $builder->withoutTrashed(); break;
            case 'with': $builder->withTrashed(); break;
            case 'only': $builder->onlyTrashed(); break;
        }

        $builder->whereHas('latest', function( $builder ) use ( $filter, $publish ) {

            switch( $publish )
            {
                case 'PUBLISHED': $builder->where( 'cms_versions.published', true ); break;
                case 'DRAFT': $builder->where( 'cms_versions.published', false ); break;
                case 'SCHEDULED': $builder->where( 'cms_versions.publish_at', '!=', null )
                    ->where( 'cms_versions.published', false ); break;
            }

            if( isset( $filter['id'] ) ) {
                $builder->whereIn( 'cms_versions.versionable_id', $filter['id'] );
            }

            if( isset( $filter['lang'] ) ) {
                $builder->where( 'cms_versions.lang', $filter['lang'] );
            }

            if( isset( $filter['editor'] ) ) {
                $builder->where( 'cms_versions.editor', 'like', $filter['editor'] . '%' );
            }

            if( isset( $filter['mime'] ) ) {
                $builder->where( 'data->mime', 'like', $filter['mime'] . '%' );
            }

            if( array_key_exists( 'name', $filter ) ) {
                $builder->where( 'data->name', 'like', $filter['name'] . '%' );
            }

            if( isset( $filter['any'] ) ) {
                $builder->whereAny( ['data->name', 'data->description', 'data->transcription'], 'like', '%' . $filter['any'] . '%' );
            }
        });

        return $builder;
    }


    /**
     * Custom query builder for pages to get pages by parent ID.
     *
     * @param  null  $rootValue
     * @param  array  $args
     * @return \Kalnoy\Nestedset\QueryBuilder
     */
    public function pages( $rootValue, array $args ) : \Kalnoy\Nestedset\QueryBuilder
    {
        $filter = $args['filter'] ?? [];
        $publish = $args['publish'] ?? null;
        $limit = (int) ( $args['first'] ?? 100 );
        $trashed = $args['trashed'] ?? null;

        $builder = Page::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        switch( $trashed ) {
            case 'without': $builder->withoutTrashed(); break;
            case 'with': $builder->withTrashed(); break;
            case 'only': $builder->onlyTrashed(); break;
        }

        if( array_key_exists( 'parent_id', $filter ) ) {
            $builder->where( 'cms_pages.parent_id', $filter['parent_id'] );
        }

        $builder->whereHas('latest', function( $builder ) use ( $filter, $publish ) {

            switch( $publish )
            {
                case 'PUBLISHED': $builder->where( 'cms_versions.published', true ); break;
                case 'DRAFT': $builder->where( 'cms_versions.published', false ); break;
                case 'SCHEDULED': $builder->where( 'cms_versions.publish_at', '!=', null )
                    ->where( 'cms_versions.published', false ); break;
            }

            if( isset( $filter['id'] ) ) {
                $builder->whereIn( 'cms_versions.versionable_id', $filter['id'] );
            }

            if( isset( $filter['lang'] ) ) {
                $builder->where( 'cms_versions.lang', (string) $filter['lang'] );
            }

            if( isset( $filter['editor'] ) ) {
                $builder->where( 'cms_versions.editor', 'like', $filter['editor'] . '%' );
            }

            if( isset( $filter['status'] ) ) {
                $builder->where( 'data->status', (int) $filter['status'] );
            }

            if( isset( $filter['cache'] ) ) {
                $builder->where( 'data->cache', (int) $filter['cache'] );
            }

            if( array_key_exists( 'to', $filter ) ) {
                $builder->where( 'data->to', (string) $filter['to'] );
            }

            if( array_key_exists( 'path', $filter ) ) {
                $builder->where( 'data->path', (string) $filter['path'] );
            }

            if( array_key_exists( 'domain', $filter ) ) {
                $builder->where( 'data->domain', (string) $filter['domain'] );
            }

            if( array_key_exists( 'tag', $filter ) ) {
                $builder->where( 'data->tag', (string) $filter['tag'] );
            }

            if( array_key_exists( 'theme', $filter ) ) {
                $builder->where( 'data->theme', (string) $filter['theme'] );
            }

            if( array_key_exists( 'type', $filter ) ) {
                $builder->where( 'data->type', (string) $filter['type'] );
            }

            if( array_key_exists( 'name', $filter ) ) {
                $builder->where( 'data->name', 'like', $filter['name'] . '%' );
            }

            if( array_key_exists( 'title', $filter ) ) {
                $builder->where( 'data->title', 'like', $filter['title'] . '%' );
            }

            if( isset( $filter['meta'] ) ) {
                $builder->where( 'aux->meta', 'like', '%' . $filter['meta'] . '%' );
            }

            if( isset( $filter['config'] ) ) {
                $builder->where( 'aux->config', 'like', '%' . $filter['config'] . '%' );
            }

            if( isset( $filter['content'] ) ) {
                $builder->where( 'aux->content', 'like', '%' . $filter['content'] . '%' );
            }

            if( isset( $filter['any'] ) ) {
                $builder->whereAny( ['aux->config', 'aux->content', 'aux->meta', 'data->name', 'data->title'], 'like', '%' . $filter['any'] . '%' );
            }

        } );

        return $builder;
    }
}
