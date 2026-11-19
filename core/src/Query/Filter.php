<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Cms\DB;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Translation\Sync;
use Laravel\Scout\Builder;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


/**
 * Shared filter logic for search builders.
 */
class Filter
{
    /**
     * Conditions of the translation states by indexed field.
     *
     * "lang" is TRUE for variants in the language and FALSE for pages without one, "stale" for
     * variants which need an update and "editor" for variants whose latest draft has been made
     * by that editor.
     *
     * @var array<string, array{lang: bool, stale?: bool, editor?: string}>
     */
    private const STATES = [
        'missing' => ['lang' => false],
        'stale' => ['lang' => true, 'stale' => true],
        'ai' => ['lang' => true, 'editor' => Sync::EDITOR],
    ];


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
     * Creates a page list search including language fallback, translation state and path prefix.
     *
     * Search terms starting with a slash match the page paths from their start instead of the texts.
     * If "path" is filtered, "path" and "domain" must match exactly. Pages are listed in the passed
     * language, or in the fallback language (see Utils::chain()) or their source language otherwise.
     * The "lang" filter only applies if no language is passed.
     *
     * @param mixed $term Search term or path prefix starting with a slash, trimmed and limited to 200 characters
     * @param array<string, mixed> $filter Validated filter values incl. "translation", "publish" and "trashed"
     * @param mixed $lang Language code of the listed pages, NULL for all variants
     * @return Builder<Model> Search builder for the pages
     */
    public static function pageList( mixed $term, array $filter, mixed $lang = null ) : Builder
    {
        $prefix = static::prefix( $term = static::term( $term ) );
        $search = static::search( PageView::class, $prefix === null ? $term : '' );
        $lang = $lang !== null ? (string) $lang : null;

        $route = array_key_exists( 'path', $filter )
            ? array_intersect_key( $filter, array_flip( ['path', 'domain'] ) )
            : [];
        $exclude = $route + ['translation' => true] + ( $lang !== null ? ['lang' => true] : [] );

        $state = $lang !== null ? ( $filter['translation'] ?? null ) : null;
        $trashed = $filter['trashed'] ?? null;

        if( $lang !== null )
        {
            // trashed variants of the language are selected by the fallback
            SearchQuery::prefer( $search, $lang, $trashed );
            $filter['trashed'] = $trashed === 'only' ? 'with' : $trashed;
        }

        // external engines only apply query callbacks to the fetched results, so the indexed fields are used
        if( $state && Scout::usesExternalSearch() )
        {
            static::indexedState( $search, (string) $lang, $state );
            $state = null;
        }

        static::pages( $search, array_diff_key( $filter, $exclude ) );

        if( $route || $state || (string) $prefix !== '' ) {
            static::pageQuery( $search, $route, (string) $prefix, $lang, $state );
        }

        return $search;
    }


    /**
     * Adds the route, path prefix and translation state conditions to the database query of the search.
     *
     * Scout keeps only one query callback, so a callback set before is called too.
     *
     * @param Builder<Model> $search Search builder
     * @param array<string, mixed> $route Exact "path" and "domain" values
     * @param string $prefix Path prefix or empty string for none
     * @param string|null $lang Language of the listed pages
     * @param mixed $state Translation state, "stale", "missing", "ai" or NULL for none
     */
    protected static function pageQuery( Builder $search, array $route, string $prefix, ?string $lang, mixed $state ) : void
    {
        $callback = $search->queryCallback;

        $search->query( function( EloquentBuilder $query ) use ( $callback, $route, $state, $lang, $prefix ) {
            $callback && $callback( $query );

            foreach( $route as $field => $value ) {
                $query->where( $query->qualifyColumn( $field ), (string) ( $value ?? '' ) );
            }

            if( $prefix !== '' ) {
                DB::wherePrefix( $query, 'cms_page_view.path', $prefix );
            }

            if( $state ) {
                static::translation( $query, (string) $lang, (string) $state );
            }
        } );
    }


    /**
     * Limits the search to a translation state by the indexed fields of external search engines.
     *
     * @param Builder<Model> $search Search builder
     * @param string $lang Language code
     * @param mixed $state Translation state, "stale", "missing" or "ai"
     */
    protected static function indexedState( Builder $search, string $lang, mixed $state ) : void
    {
        $conditions = static::state( (string) $state );
        $conditions['lang'] ? $search->where( 'lang', $lang ) : $search->whereNotIn( 'lang', [$lang] );

        if( isset( $conditions['stale'] ) ) {
            $search->where( 'stale', $conditions['stale'] );
        }

        if( isset( $conditions['editor'] ) ) {
            $search->where( 'editor', $conditions['editor'] );
        }
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
        return $class::search( static::term( $term ) )->searchFields( 'draft' );
    }


    /**
     * Limits the pages queried with the language fallback to a translation state.
     *
     * "stale" are variants in the language which need an update, "missing" are pages without
     * a variant in the language and "ai" are variants whose latest draft was made by AI.
     *
     * "stale" and "ai" select variants of the language only, so the fallback is replaced by the
     * variants of that language. "missing" keeps the fallback and lists the variants of the
     * fallback languages (see Utils::chain()) or the source variants of the pages instead.
     *
     * @template TModel of Model
     * @param EloquentBuilder<TModel> $query Page query with the fallback of the language
     * @param string $lang Language code
     * @param string $state Translation state, "stale", "missing" or "ai"
     * @return EloquentBuilder<TModel> Same query for fluent calls
     */
    public static function translation( EloquentBuilder $query, string $lang, string $state ) : EloquentBuilder
    {
        $conditions = static::state( $state );
        $table = $query->getModel()->getTable();
        $base = $query->getQuery();

        // trashed variants of the language are included if the fallback includes them
        $trashed = $base instanceof PageBuilder && ( $base->variant()[2] ?? false );

        if( $query instanceof PageQuery ) {
            $conditions['lang'] ? $query->language( $lang, $trashed ) : $query->fallback( $lang, $trashed );
        }

        $query->where( $table . '.lang', $conditions['lang'] ? '=' : '<>', $lang );

        if( isset( $conditions['stale'] ) ) {
            $query->where( $table . '.stale', $conditions['stale'] );
        }

        // drafts made by AI are recognized by the editor of the latest version
        if( isset( $conditions['editor'] ) )
        {
            $editor = $conditions['editor'];

            $query->whereExists( fn( $q ) => $q->selectRaw( '1' )
                ->from( 'cms_versions' )
                ->whereColumn( 'cms_versions.id', $table . '.latest_id' )
                ->where( 'cms_versions.editor', $editor )
            );
        }

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
     * Returns the path prefix if the search term starts with a slash.
     *
     * @param string $term Normalized search term, see term()
     * @return string|null Path prefix without leading and trailing slashes or NULL if the term isn't a path
     */
    protected static function prefix( string $term ) : ?string
    {
        return str_starts_with( $term, '/' ) ? trim( $term, '/' ) : null;
    }


    /**
     * Returns the trimmed search term limited to 200 characters.
     *
     * @param mixed $term Search term
     * @return string Normalized search term
     */
    protected static function term( mixed $term ) : string
    {
        return mb_substr( trim( (string) $term ), 0, 200 );
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
     * Returns the conditions of the translation state.
     *
     * @param string $state Translation state, "stale", "missing" or "ai"
     * @return array{lang: bool, stale?: bool, editor?: string} Conditions by indexed field, see STATES
     * @throws \InvalidArgumentException If the translation state is unknown
     */
    protected static function state( string $state ) : array
    {
        return self::STATES[$state] ?? throw new \InvalidArgumentException( sprintf( 'Invalid translation state "%1$s"', $state ) );
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
