<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


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
     * @param  array<string, mixed>  $args
     * @return \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\Element>
     */
    public function elements( $rootValue, array $args ) : Builder
    {
        $filter = $args['filter'] ?? [];
        $limit = (int) ( $args['first'] ?? 100 );

        $builder = Element::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        $this->trashed( $builder, $args['trashed'] ?? null );

        $builder->select( 'cms_elements.*' )
            ->join( 'cms_versions', 'cms_elements.latest_id', '=', 'cms_versions.id' );

        $this->publish( $builder, $args['publish'] ?? null );
        $this->filter( $builder, $filter, 'cms_elements', Element::class );

        if( isset( $filter['type'] ) ) {
            $builder->where( 'cms_versions.data->type', (string) $filter['type'] );
        }

        return $builder;
    }


    /**
     * Custom query builder for files to search for.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return \Illuminate\Database\Eloquent\Builder<\Aimeos\Cms\Models\File>
     */
    public function files( $rootValue, array $args ) : Builder
    {
        $filter = $args['filter'] ?? [];
        $limit = (int) ( $args['first'] ?? 100 );

        $builder = File::withCount( 'byversions' )->skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        $this->trashed( $builder, $args['trashed'] ?? null );

        $builder->join( 'cms_versions', 'cms_files.latest_id', '=', 'cms_versions.id' );

        $this->publish( $builder, $args['publish'] ?? null );
        $this->filter( $builder, $filter, 'cms_files', File::class );

        if( isset( $filter['mime'] ) ) {
            $builder->where( 'cms_versions.data->mime', 'like', $filter['mime'] . '%' );
        }

        return $builder;
    }


    /**
     * Custom query builder for pages to get pages by parent ID.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return \Aimeos\Nestedset\QueryBuilder<\Aimeos\Cms\Models\Page>
     */
    public function pages( $rootValue, array $args ) : \Aimeos\Nestedset\QueryBuilder
    {
        $filter = $args['filter'] ?? [];
        $limit = (int) ( $args['first'] ?? 100 );

        $builder = Page::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        $this->trashed( $builder, $args['trashed'] ?? null );

        if( array_key_exists( 'parent_id', $filter ) ) {
            $builder->where( 'cms_pages.parent_id', $filter['parent_id'] );
        }

        $builder->select( 'cms_pages.*' )
            ->join( 'cms_versions', 'cms_pages.latest_id', '=', 'cms_versions.id' );

        $this->publish( $builder, $args['publish'] ?? null );
        $this->filter( $builder, $filter, 'cms_pages', Page::class );

        if( isset( $filter['status'] ) ) {
            $builder->where( 'cms_versions.data->status', (int) $filter['status'] );
        }

        if( isset( $filter['cache'] ) ) {
            $builder->where( 'cms_versions.data->cache', (int) $filter['cache'] );
        }

        if( array_key_exists( 'to', $filter ) ) {
            $builder->where( 'cms_versions.data->to', (string) $filter['to'] );
        }

        if( array_key_exists( 'path', $filter ) ) {
            $builder->where( 'cms_versions.data->path', (string) $filter['path'] );
        }

        if( array_key_exists( 'domain', $filter ) ) {
            $builder->where( 'cms_versions.data->domain', (string) $filter['domain'] );
        }

        if( array_key_exists( 'tag', $filter ) ) {
            $builder->where( 'cms_versions.data->tag', (string) $filter['tag'] );
        }

        if( array_key_exists( 'theme', $filter ) ) {
            $builder->where( 'cms_versions.data->theme', (string) $filter['theme'] );
        }

        if( array_key_exists( 'type', $filter ) ) {
            $builder->where( 'cms_versions.data->type', (string) $filter['type'] );
        }

        return $builder;
    }


    /**
     * Applies trashed filter to the query builder.
     *
     * @param Builder<Page>|Builder<Element>|Builder<File> $builder Query builder
     * @param string|null $trashed Trashed filter value
     */
    private function trashed( $builder, ?string $trashed ) : void
    {
        switch( $trashed ) {
            case 'without': $builder->withoutTrashed(); break;
            case 'with': $builder->withTrashed(); break;
            case 'only': $builder->onlyTrashed(); break;
        }
    }


    /**
     * Applies publish status filter to the query builder.
     *
     * @param Builder<Page>|Builder<Element>|Builder<File> $builder Query builder
     * @param string|null $publish Publish filter value
     */
    private function publish( $builder, ?string $publish ) : void
    {
        switch( $publish )
        {
            case 'PUBLISHED': $builder->where( 'cms_versions.published', true ); break;
            case 'DRAFT': $builder->where( 'cms_versions.published', false ); break;
            case 'SCHEDULED': $builder->where( 'cms_versions.publish_at', '!=', null )
                ->where( 'cms_versions.published', false ); break;
        }
    }


    /**
     * Applies common filters (id, lang, editor, any) to the query builder.
     *
     * @param Builder<Page>|Builder<Element>|Builder<File> $builder Query builder
     * @param array<string, mixed> $filter Filter values
     * @param string $table Database table name
     * @param string $model Model class name
     */
    private function filter( $builder, array $filter, string $table, string $model ) : void
    {
        if( isset( $filter['id'] ) ) {
            $builder->whereIn( $table . '.id', $filter['id'] );
        }

        if( isset( $filter['lang'] ) ) {
            $builder->where( 'cms_versions.lang', (string) $filter['lang'] );
        }

        if( isset( $filter['editor'] ) ) {
            $builder->where( 'cms_versions.editor', (string) $filter['editor'] );
        }

        if( isset( $filter['any'] ) )
        {
            $ids = $model::search( mb_substr( trim( $filter['any'] ), 0, 200 ) )
                ->searchFields( 'draft' ) // @phpstan-ignore method.notFound
                ->take( 1000 )
                ->keys();

            $builder->whereIn( $table . '.id', $ids->all() );
        }
    }
}
