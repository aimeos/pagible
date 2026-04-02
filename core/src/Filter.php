<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Laravel\Scout\Builder;


/**
 * Shared filter logic for search builders.
 */
class Filter
{
    /**
     * Apply element-specific filters to a Scout builder.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function elements( Builder $builder, array $filter ) : Builder
    {
        if( isset( $filter['id'] ) ) {
            $builder->whereIn( 'id', (array) $filter['id'] );
        }

        if( array_key_exists( 'lang', $filter ) ) {
            $builder->where( 'lang', $filter['lang'] );
        }

        if( isset( $filter['type'] ) ) {
            $builder->where( 'type', (string) $filter['type'] );
        }

        if( isset( $filter['editor'] ) ) {
            $builder->where( 'editor', (string) $filter['editor'] );
        }

        static::publish( $builder, $filter['publish'] ?? null );
        static::trashed( $builder, $filter['trashed'] ?? null );

        return $builder;
    }


    /**
     * Apply file-specific filters to a Scout builder.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function files( Builder $builder, array $filter ) : Builder
    {
        if( isset( $filter['id'] ) ) {
            $builder->whereIn( 'id', (array) $filter['id'] );
        }

        if( array_key_exists( 'lang', $filter ) ) {
            $builder->where( 'lang', $filter['lang'] );
        }

        if( isset( $filter['mime'] ) ) {
            $builder->where( 'mime', (string) $filter['mime'] );
        }

        if( isset( $filter['editor'] ) ) {
            $builder->where( 'editor', (string) $filter['editor'] );
        }

        static::publish( $builder, $filter['publish'] ?? null );
        static::trashed( $builder, $filter['trashed'] ?? null );

        return $builder;
    }


    /**
     * Apply page-specific filters to a Scout builder.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function pages( Builder $builder, array $filter ) : Builder
    {
        if( isset( $filter['id'] ) ) {
            $builder->whereIn( 'id', (array) $filter['id'] );
        }

        if( array_key_exists( 'parent_id', $filter ) ) {
            $builder->where( 'parent_id', $filter['parent_id'] );
        }

        if( array_key_exists( 'lang', $filter ) ) {
            $builder->where( 'lang', $filter['lang'] );
        }

        if( isset( $filter['status'] ) ) {
            $builder->where( 'status', (int) $filter['status'] );
        }

        if( isset( $filter['cache'] ) ) {
            $builder->where( 'cache', (int) $filter['cache'] );
        }

        foreach( ['domain', 'editor', 'path', 'tag', 'theme', 'to', 'type'] as $field )
        {
            if( isset( $filter[$field] ) ) {
                $builder->where( $field, (string) $filter[$field] );
            }
        }

        static::publish( $builder, $filter['publish'] ?? null );
        static::trashed( $builder, $filter['trashed'] ?? null );

        return $builder;
    }


    /**
     * Apply publish-status filter.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param string|null $publish PUBLISHED, DRAFT, or SCHEDULED
     */
    protected static function publish( $builder, ?string $publish ) : void
    {
        match( $publish ) {
            'PUBLISHED' => $builder->where( 'published', true ),
            'DRAFT' => $builder->where( 'published', false ),
            'SCHEDULED' => $builder->where( 'published', false )->where( 'scheduled', true ),
            default => null,
        };
    }


    /**
     * Apply soft-delete filter.
     *
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param string|null $trashed without, with, or only
     */
    protected static function trashed( $builder, ?string $trashed ) : void
    {
        match( $trashed ) {
            'with' => $builder->withTrashed(),
            'only' => $builder->onlyTrashed(),
            default => null,
        };
    }
}
