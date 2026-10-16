<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Query\PageQuery;
use Aimeos\Cms\Query\SearchQuery;
use Aimeos\Cms\Scout;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;


/**
 * Search index of the page view
 *
 * Each page variant is indexed separately using the variant ID as key.
 */
trait PageSearch
{
    /**
     * Returns the key of the search index entry, which is the variant ID.
     *
     * @return mixed Variant ID or the page ID for the first variant
     */
    public function getScoutKey(): mixed
    {
        return $this->getAttribute( 'variant_id' ) ?? $this->getKey();
    }


    /**
     * Returns the view column of the search index key, each page variant is indexed separately.
     *
     * @return string Column name
     */
    public function getScoutKeyName(): string
    {
        return 'variant_id';
    }


    /**
     * Returns the name of the external search index.
     *
     * Keeps the name of the index used before the pages were read from the page view,
     * so the index settings like filterable and sortable attributes remain valid.
     *
     * @return string Index name
     */
    public function searchableAs(): string
    {
        return config( 'scout.prefix' ) . 'cms_pages';
    }


    /**
     * Gets the page variants matching the search index keys.
     *
     * @param Builder<static> $builder Scout search builder
     * @param array<int, mixed> $ids Variant IDs
     * @return EloquentBuilder<static> Query for the page variants
     */
    public function queryScoutModelsByIds( Builder $builder, array $ids )
    {
        $query = $this->newQuery();
        $query->withTrashed();

        if( !SearchQuery::fallback( $query, $builder ) ) {
            $query->allVariants( trashed: true );
        }

        SearchQuery::columns( $query, $builder );

        if( $builder->queryCallback ) {
            call_user_func( $builder->queryCallback, $query );
        }

        return $query->whereIn( $this->qualifyColumn( $this->getScoutKeyName() ), $ids );
    }


    /**
     * Returns the searchable data for the page.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $attrs = ['domain', 'lang', 'path', 'to', 'tag', 'name', 'title', 'meta', 'content', 'deleted_at', 'variant_deleted_at', 'latest_id', 'stale'];

        // bulk index + changed content check for performance reasons
        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        $draft = '';
        $version = $this->latest;
        $data = $version?->data;

        if( $version )
        {
            $draft = mb_strtolower( trim(
                ( $data->path ?? '' ) . "\n"
                . ( $data->to ?? '' ) . "\n"
                . (string) $version
            ) );
        }

        $content = '';

        if( !$this->trashed() && $this->getAttribute( 'variant_deleted_at' ) === null )
        {
            $content = mb_strtolower( trim(
                $this->path . "\n"
                . $this->to . "\n"
                . (string) $this
            ) );
        }

        return [
            'draft' => $draft,
            'content' => $content,
            'tenant_id' => $this->tenant_id ?? '',
            'parent_id' => $this->parent_id,

            // published values for frontend search
            'lang' => $this->lang ?? '',
            'path' => $this->path ?? '',
            'domain' => $this->domain ?? '',

            // draft values for backend search
            'editor' => $version->editor ?? '',
            'status' => (int) ( $data->status ?? 0 ),
            'cache' => (int) ( $data->cache ?? 0 ),
            'to' => $data->to ?? '',
            'tag' => $data->tag ?? '',
            'theme' => $data->theme ?? '',
            'type' => $data->type ?? '',
            'published' => (bool) ( $version->published ?? false ),
            'scheduled' => (int) ( $data->scheduled ?? 0 ),

            // frontend access hint for fast filtering
            'restricted' => $this->restricted(),
        ] + ( Scout::usesExternalSearch() ? [
            // languages for searches with language fallback in external engines
            'langs' => $this->searchLangs( trashed: false ),
            'langs_trashed' => $this->searchLangs( trashed: true ),
            // translation state for page lists of a language
            'stale' => (bool) $this->stale,
        ] : [] );
    }


    /**
     * Returns the languages a page variant is listed for by searches with language fallback.
     *
     * Variants are listed for their own language and source variants also for the languages
     * from "cms.locales" the page has no variant for.
     *
     * @param bool $trashed TRUE if trashed variants are listed too
     * @return array<int, string> Language codes
     */
    protected function searchLangs( bool $trashed ) : array
    {
        $lang = (string) $this->lang;

        if( !$trashed && $this->getAttribute( 'variant_deleted_at' ) !== null ) {
            return [];
        }

        if( $lang !== (string) $this->source ) {
            return [$lang];
        }

        $variants = $this->languages;
        $existing = ( $trashed ? $variants : $variants->whereNull( 'deleted_at' ) )->pluck( 'lang' )->all();
        $locales = Utils::locales();

        return array_values( array_unique( [$lang, ...array_diff( $locales, $existing )] ) );
    }


    /**
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param EloquentBuilder<static> $query
     * @return EloquentBuilder<static>
     */
    protected function makeAllSearchableUsing( $query )
    {
        if( $query instanceof PageQuery ) {
            $query->allVariants( trashed: true );
        }

        return $query->select( [...self::SELECT_COLUMNS, 'variant_deleted_at', 'stale'] )->withCount( 'access' )->with( [
            'elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux'] ),
            'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
        ] )->when( Scout::usesExternalSearch(), fn( $q ) => $q->with( 'languages' ) );
    }
}
