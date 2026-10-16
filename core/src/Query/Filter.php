<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\Translation\Sync;
use Laravel\Scout\Builder;
use Aimeos\Cms\Models\Base;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


/**
 * Shared filter logic for search builders.
 */
class Filter
{
    /**
     * Apply element-specific filters to a Scout builder.
     *
     * @param Builder<Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return Builder<Model>
     */
    public static function elements( Builder $builder, array $filter ) : Builder
    {
        return static::common( $builder, $filter, ['lang', 'type', 'editor'] );
    }


    /**
     * Apply file-specific filters to a Scout builder.
     *
     * @param Builder<Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return Builder<Model>
     */
    public static function files( Builder $builder, array $filter ) : Builder
    {
        if( isset( $filter['mime'] ) ) {
            $builder->whereIn( 'mime', (array) $filter['mime'] );
        }

        return static::common( $builder, $filter, ['lang', 'editor'] );
    }


    /**
     * Apply page-specific filters to a Scout builder.
     *
     * @param Builder<Model> $builder
     * @param array<string, mixed> $filter Validated filter values (may include 'publish' and 'trashed')
     * @return Builder<Model>
     */
    public static function pages( Builder $builder, array $filter ) : Builder
    {
        if( array_key_exists( 'parent_id', $filter ) ) {
            $builder->where( 'parent_id', $filter['parent_id'] );
        }

        foreach( ['status', 'cache'] as $field )
        {
            if( array_key_exists( $field, $filter ) ) {
                $builder->where( $field, (int) ( $filter[$field] ?? 0 ) );
            }
        }

        return static::common( $builder, $filter, ['lang', 'domain', 'editor', 'path', 'tag', 'theme', 'to', 'type'] );
    }


    /**
     * Creates a Scout builder searching the draft texts of the given model.
     *
     * @param class-string<Base> $class Model class, e.g. PageView::class
     * @param mixed $term Search term, trimmed and limited to 200 characters
     * @return Builder<Model>
     */
    public static function search( string $class, mixed $term ) : Builder
    {
        return $class::search( mb_substr( trim( (string) $term ), 0, 200 ) )->searchFields( 'draft' );
    }


    /**
     * Limits the pages queried with the language fallback to a translation state.
     *
     * "stale" are variants in the language which need an update, "missing" are pages without
     * a variant in the language and "ai" are variants whose latest draft was made by AI.
     *
     * Each state selects variants of one language only, so the fallback is replaced by the
     * variants of that language or the source variants of the pages without one. This avoids
     * the fallback condition which can't use the indexes and would check all variants.
     *
     * @template TModel of Model
     * @param EloquentBuilder<TModel> $query Page query with the fallback of the language
     * @param string $lang Language code
     * @param string $state Translation state, "stale", "missing" or "ai"
     * @return EloquentBuilder<TModel> Same query for fluent calls
     */
    public static function translation( EloquentBuilder $query, string $lang, string $state ) : EloquentBuilder
    {
        if( !in_array( $state, ['missing', 'stale', 'ai'], true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid translation state "%1$s"', $state ) );
        }

        $table = $query->getModel()->getTable();
        $base = $query->getQuery();

        // trashed variants of the language are included if the fallback includes them
        $trashed = $base instanceof PageBuilder && ( $base->variant()[2] ?? false );

        if( $query instanceof PageQuery ) {
            $state === 'missing' ? $query->language( null ) : $query->language( $lang, $trashed );
        }

        match( $state ) {
            'missing' => $query->where( $table . '.lang', '<>', $lang )
                ->when( !$trashed, fn( $q ) => $q->whereNull( $table . '.variant_deleted_at' ) )
                ->whereNotExists( fn( $q ) => $q->selectRaw( '1' )
                    ->from( 'cms_page_variants as m' )
                    ->whereColumn( 'm.page_id', $table . '.id' )
                    ->where( 'm.lang', $lang )
                    ->when( !$trashed, fn( $q ) => $q->whereNull( 'm.deleted_at' ) )
                ),
            'stale' => $query->where( $table . '.lang', $lang )->where( $table . '.stale', true ),
            'ai' => $query->where( $table . '.lang', $lang )->whereExists( fn( $q ) => $q->selectRaw( '1' )
                ->from( 'cms_versions' )
                ->whereColumn( 'cms_versions.id', $table . '.latest_id' )
                ->where( 'cms_versions.editor', Sync::EDITOR )
            ),
        };

        return $query;
    }


    /**
     * Apply the ID, string field, publish-status and soft-delete filters.
     *
     * @param Builder<Model> $builder
     * @param array<string, mixed> $filter Validated filter values
     * @param array<int, string> $fields Names of the string fields to filter by
     * @return Builder<Model>
     */
    protected static function common( Builder $builder, array $filter, array $fields ) : Builder
    {
        if( array_key_exists( 'id', $filter ) ) {
            $builder->whereIn( 'id', (array) $filter['id'] );
        }

        foreach( $fields as $field )
        {
            if( array_key_exists( $field, $filter ) ) {
                $builder->where( $field, (string) ( $filter[$field] ?? '' ) );
            }
        }

        static::publish( $builder, $filter['publish'] ?? null );
        static::trashed( $builder, $filter['trashed'] ?? null );

        return $builder;
    }


    /**
     * Apply publish-status filter.
     *
     * @param Builder<Model> $builder
     * @param string|null $publish PUBLISHED, DRAFT, or SCHEDULED
     */
    protected static function publish( Builder $builder, ?string $publish ) : void
    {
        match( $publish ) {
            'PUBLISHED' => $builder->where( 'published', true ),
            'DRAFT' => $builder->where( 'published', false ),
            'SCHEDULED' => $builder->where( 'published', false )->where( 'scheduled', 1 ),
            default => null,
        };
    }


    /**
     * Apply soft-delete filter.
     *
     * @param Builder<Model> $builder
     * @param string|null $trashed without, with, or only
     */
    protected static function trashed( Builder $builder, ?string $trashed ) : void
    {
        match( $trashed ) {
            'with' => $builder->withTrashed(),
            'only' => $builder->onlyTrashed(),
            default => null,
        };
    }
}
