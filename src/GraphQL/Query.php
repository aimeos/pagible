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

        switch( $args['publish'] ?? null )
        {
            case 'PUBLISHED':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', true );
                });
                break;
            case 'DRAFT':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', false );
                });
                break;
            case 'SCHEDULED':
                $builder->whereHas('versions', function( $builder ) {
                    $builder->where( 'publish_at', '!=', null )
                        ->where( 'published', false );
                });
                break;
        }

        $builder->whereHas('latest', function( $builder ) use ( $filter ) {

            if( isset( $filter['id'] ) ) {
                $builder->whereIn( 'versionable_id', $filter['id'] );
            }

            if( isset( $filter['lang'] ) ) {
                $builder->where( 'lang', $filter['lang'] );
            }

            if( isset( $filter['editor'] ) ) {
                $builder->where( 'editor', 'like', $filter['editor'] . '%' );
            }

            if( isset( $filter['type'] ) ) {
                $builder->where( 'data->type', (string) $filter['type'] );
            }

            if( isset( $filter['name'] ) ) {
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

        switch( $args['publish'] ?? null )
        {
            case 'PUBLISHED':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', true );
                });
                break;
            case 'DRAFT':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', false );
                });
                break;
            case 'SCHEDULED':
                $builder->whereHas('versions', function( $builder ) {
                    $builder->where( 'publish_at', '!=', null )
                        ->where( 'published', false );
                });
                break;
        }

        $builder->whereHas('latest', function( $builder ) use ( $filter ) {

            if( isset( $filter['id'] ) ) {
                $builder->whereIn( 'versionable_id', $filter['id'] );
            }

            if( isset( $filter['lang'] ) ) {
                $builder->where( 'lang', $filter['lang'] );
            }

            if( isset( $filter['editor'] ) ) {
                $builder->where( 'editor', 'like', $filter['editor'] . '%' );
            }

            if( isset( $filter['mime'] ) ) {
                $builder->where( 'data->mime', 'like', $filter['mime'] . '%' );
            }

            if( isset( $filter['name'] ) ) {
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
        $limit = (int) ( $args['first'] ?? 100 );
        $trashed = $args['trashed'] ?? null;

        $builder = Page::skip( max( ( $args['page'] ?? 1 ) - 1, 0 ) * $limit )
            ->take( min( max( $limit, 1 ), 100 ) );

        switch( $trashed ) {
            case 'without': $builder->withoutTrashed(); break;
            case 'with': $builder->withTrashed(); break;
            case 'only': $builder->onlyTrashed(); break;
        }

        switch( $args['publish'] ?? null )
        {
            case 'PUBLISHED':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', true );
                });
                break;
            case 'DRAFT':
                $builder->whereHas('latest', function( $builder ) {
                    $builder->where( 'published', false );
                });
                break;
            case 'SCHEDULED':
                $builder->whereHas('versions', function( $builder ) {
                    $builder->where( 'publish_at', '!=', null )
                        ->where( 'published', false );
                });
                break;
        }

        $builder->where( function( $builder )  use ( $filter, $args ) {

            $builder->whereHas('latest', function( $builder ) use ( $filter, $args ) {

                if( array_key_exists( 'parent_id', $args['filter'] ) ) {
                    $builder->where( 'cms_pages.parent_id', $args['filter']['parent_id'] );
                }

                if( isset( $filter['id'] ) ) {
                    $builder->whereIn( 'versionable_id', $filter['id'] );
                }

                if( isset( $filter['editor'] ) ) {
                    $builder->where( 'editor', 'like', $filter['editor'] . '%' );
                }

                if( array_key_exists( 'to', $args['filter'] ) ) {
                    $builder->where( 'data->to', (string) $args['filter']['to'] );
                }

                if( array_key_exists( 'path', $args['filter'] ) ) {
                    $builder->where( 'data->path', (string) $args['filter']['path'] );
                }

                if( array_key_exists( 'domain', $args['filter'] ) ) {
                    $builder->where( 'data->domain', (string) $args['filter']['domain'] );
                }

                if( isset( $filter['lang'] ) ) {
                    $builder->where( 'lang', (string) $filter['lang'] );
                }

                if( isset( $filter['tag'] ) ) {
                    $builder->where( 'data->tag', (string) $filter['tag'] );
                }

                if( isset( $filter['theme'] ) ) {
                    $builder->where( 'data->theme', (string) $filter['theme'] );
                }

                if( isset( $filter['type'] ) ) {
                    $builder->where( 'data->type', (string) $filter['type'] );
                }

                if( isset( $filter['status'] ) ) {
                    $builder->where( 'data->status', (int) $filter['status'] );
                }

                if( isset( $filter['cache'] ) ) {
                    $builder->where( 'data->cache', (int) $filter['cache'] );
                }

                if( isset( $filter['name'] ) ) {
                    $builder->where( 'data->name', 'like', $filter['name'] . '%' );
                }

                if( isset( $filter['title'] ) ) {
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

        } );

        return $builder;
    }
}
