<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\PageAttributes;
use Aimeos\Cms\Concerns\PageRestrictions;
use Aimeos\Cms\Concerns\PageSearch;
use Aimeos\Cms\Concerns\PageTree;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Query\PageQuery;
use Aimeos\Nestedset\NodeTrait;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;


/**
 * Page view model
 *
 * Read model of the page tree (cms_pages, Page model) joined with one of the
 * language variants (cms_page_variants, PageVariant model), read from the
 * cms_page_view view joining both tables. By default, the variant of the source
 * language is used.
 *
 * Pages are split by responsibility:
 * - Page writes the tree, e.g. moving, trashing or checking it with fixTree()/isBroken()
 * - PageVariant writes the language specific content of one page
 * - PageView reads pages for navigation, filtering, search and templates and is read-only
 *
 * Tree relations are in the PageTree trait, the search index in PageSearch and the
 * frontend access rules in PageRestrictions.
 *
 * @property string $id
 * @property string $variant_id
 * @property string $source
 * @property array<string, string> $hashes
 * @property bool $stale
 * @property string $tenant_id
 * @property string $tag
 * @property string $lang
 * @property string $path
 * @property string $domain
 * @property string $to
 * @property string $name
 * @property string $title
 * @property string $type
 * @property string $theme
 * @property \stdClass $meta
 * @property \stdClass $config
 * @property \stdClass $content
 * @property int $status
 * @property int $cache
 * @property string $editor
 * @property string|null $parent_id
 * @property string|null $latest_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Aimeos\Nestedset\Collection<int, Nav>|null $subtree
 * @property bool $access_allowed
 * @property bool $access_exists
 * @property-read Collection<int, PageView> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageAccess> $access
 * @property-read Page $page
 * @property-read PageVariant $variant
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static PageQuery<static> language(?string $lang, bool $trashed = false)
 * @method static PageQuery<static> allVariants(bool $trashed = false)
 * @method static PageQuery<static> fallback(string $lang, bool $trashed = false)
 * @method static PageQuery<static> visible(string $lang)
 * @method static PageQuery<static> localized(string $lang, bool $editor)
 */
class PageView extends Base
{
    use PageAttributes;
    use PageRestrictions;
    use PageSearch;
    use NodeTrait, PageTree {
        PageTree::ancestors insteadof NodeTrait;
        PageTree::children insteadof NodeTrait;
        PageTree::parent insteadof NodeTrait;
        PageTree::newNestedSetQuery insteadof NodeTrait;
        PageTree::deleteDescendants insteadof NodeTrait;
        PageTree::restoreDescendants insteadof NodeTrait;
    }

    public const PERM = 'page';
    public const READONLY = 'Page views are read-only, write pages using the Page and PageVariant models';
    protected const REFS = ['files', 'elements'];

    /** @var list<string> Columns required for Page lifecycle operations */
    public const REQUIRED_COLUMNS = [
        'id', 'variant_id', 'tenant_id', 'parent_id', 'source', 'lang', 'path', 'domain', 'editor', 'latest_id', 'deleted_at',
        NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH,
    ];

    /** @var list<string> Optional columns available for selective Page responses */
    public const RESPONSE_COLUMNS = [
        'name', 'title', 'tag', 'to', 'type', 'theme', 'meta', 'config',
        'content', 'status', 'cache', 'stale', 'created_at', 'updated_at',
    ];

    /** @var list<string> Columns needed for memory-efficient Page queries */
    public const SELECT_COLUMNS = [
        'id', 'variant_id', 'tenant_id', 'parent_id', 'source', 'path', 'domain', 'name', 'title',
        'tag', 'lang', 'to', 'type', 'theme', 'meta', 'content', 'status', 'cache',
        'editor', 'latest_id', 'created_at', 'updated_at', 'deleted_at',
        NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = PageVariant::DEFAULTS;

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = PageVariant::CASTS;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tag',
        'lang',
        'path',
        'domain',
        'to',
        'name',
        'title',
        'type',
        'theme',
        'status',
        'cache',
    ];

    /**
     * The attributes that are returned by toArray()
     *
     * @var list<string>
     */
    protected $visible = [
        'tag',
        'lang',
        'path',
        'domain',
        'to',
        'name',
        'title',
        'type',
        'theme',
        'status',
        'cache',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_page_view';


    /**
     * Selects the variants of the pages read from the page view.
     */
    protected static function booted() : void
    {
        static::addGlobalScope( new \Aimeos\Cms\Scopes\Variant() );
    }


    /**
     * Returns the text content of the page.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        $items = collect( (array) $this->content )->merge( $this->elements );

        return trim( implode( "\n", [
            $this->tag ?? '',
            $this->name ?? '',
            $this->title ?? '',
            $this->meta->{'meta-tags'}->data->description ?? '',
            ...\Aimeos\Cms\Scout::text( $items ),
        ] ) );
    }


    /**
     * Returns the name of the class owning the page versions.
     *
     * @return string Class name
     */
    public function getMorphClass()
    {
        return static::versionType();
    }


    /**
     * Returns the type stored in the versions referencing the page variants.
     *
     * @return string Versionable type
     */
    public static function versionType() : string
    {
        return PageVariant::class;
    }


    /**
     * Returns the attribute name of the key the versions are referencing.
     *
     * @return string Attribute name
     */
    public function getVersionKeyName() : string
    {
        return 'variant_id';
    }


    /**
     * Returns the columns which receive a unique ID, the page and its variant have their own IDs.
     *
     * @return array<int, string> Column names
     */
    public function uniqueIds() : array
    {
        return ['id', 'variant_id'];
    }


    /**
     * Creates a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return PageQuery<self>
     */
    public function newEloquentBuilder( $query ) : PageQuery
    {
        return new PageQuery( $query );
    }


    /**
     * Get a new query builder instance for the connection.
     *
     * @return PageBuilder
     */
    protected function newBaseQueryBuilder()
    {
        $conn = $this->getConnection();
        return new PageBuilder( $conn, $conn->getQueryGrammar(), $conn->getPostProcessor() );
    }


    /**
     * Returns the references of the latest versions of the given pages in the language.
     *
     * @param array<string> $ids Page IDs
     * @param string|null $lang Language of the variants or NULL for the source variants
     * @return array<string, array<string, array<string>>> Referenced IDs by relation, keyed by latest version ID
     */
    public static function refs( array $ids, ?string $lang = null ) : array
    {
        if( empty( $ids ) ) {
            return [];
        }

        return static::versionRefs( static::withTrashed()->language( $lang, true )->whereIn( 'id', $ids )
            ->whereNotNull( 'latest_id' )->pluck( 'latest_id' )->all() );
    }


    /**
     * Returns the language variants of the page and their state.
     *
     * @return list<array{id: string|null, lang: string, source: bool, state: string, published: bool}> Variant list
     */
    public function variantList() : array
    {
        $id = (string) $this->id;
        return self::variantLists( [$id => (string) $this->source] )[$id];
    }


    /**
     * Returns the language variants of the pages and their state.
     *
     * The state is "current", "stale" (source changed since the last update), "trashed" or
     * "missing" for languages from "cms.locales" without a variant. "published" is TRUE if
     * the latest version of the variant is published.
     *
     * @param array<string, string> $pages Source language codes keyed by page ID
     * @return array<string, list<array{id: string|null, lang: string, source: bool, state: string, published: bool}>> Variant lists keyed by page ID
     */
    public static function variantLists( array $pages ) : array
    {
        $variants = [];

        // the publish state of the latest version is joined to avoid a second ID list
        foreach( array_chunk( array_map( 'strval', array_keys( $pages ) ), 1000 ) as $chunk )
        {
            $list = PageVariant::withTrashed()
                ->leftJoin( 'cms_versions', 'cms_versions.id', '=', 'cms_page_variants.latest_id' )
                ->whereIn( 'cms_page_variants.page_id', $chunk )
                ->orderBy( 'cms_page_variants.lang' )
                ->get( [
                    'cms_page_variants.id', 'cms_page_variants.page_id', 'cms_page_variants.lang', 'cms_page_variants.stale',
                    'cms_page_variants.deleted_at', 'cms_versions.published',
                ] );

            foreach( $list as $variant ) {
                $variants[] = $variant;
            }
        }

        $locales = array_fill_keys( array_map( 'strval', (array) config( 'cms.locales', [] ) ), null );
        $result = array_fill_keys( array_keys( $pages ), $locales );

        foreach( $variants as $variant )
        {
            $result[$variant->page_id][$variant->lang] = [
                'id' => $variant->id,
                'lang' => $variant->lang,
                'source' => $variant->lang === $pages[$variant->page_id],
                'state' => match( true ) {
                    $variant->trashed() => 'trashed',
                    $variant->stale => 'stale',
                    default => 'current',
                },
                'published' => (bool) $variant->getAttribute( 'published' ),
            ];
        }

        foreach( $result as $id => $list )
        {
            foreach( $list as $lang => $entry ) {
                $list[$lang] = $entry ?? ['id' => null, 'lang' => (string) $lang, 'source' => false, 'state' => 'missing', 'published' => false];
            }

            $result[$id] = array_values( $list );
        }

        return $result;
    }


    /**
     * Get the shared element for the page.
     *
     * @return BelongsToMany<Element, $this> Eloquent relationship to the elements attached to the page
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_page_element', 'variant_id', 'element_id', 'variant_id' );
    }


    /**
     * Get all files referenced by the versioned data.
     *
     * @return BelongsToMany<File, $this> Eloquent relationship to the files
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_page_file', 'variant_id', 'file_id', 'variant_id' );
    }


    /**
     * Maps the elements by ID automatically.
     *
     * @return Collection<string, Element> List elements with ID as keys and element models as values
     */
    public function getElementsAttribute() : Collection
    {
        $this->relationLoaded( 'elements' ) ?: $this->load( 'elements' );
        return $this->getRelation( 'elements' )->pluck( null, 'id' );
    }


    /**
     * Maps the files by ID automatically.
     *
     * @return Collection<string, File> List files with ID as keys and file models as values
     */
    public function getFilesAttribute() : Collection
    {
        $this->relationLoaded( 'files' ) ?: $this->load( 'files' );
        return $this->getRelation( 'files' )->pluck( null, 'id' );
    }


    /**
     * Relation to the page node for writing the structure, including trashed pages.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page() : BelongsTo
    {
        return $this->belongsTo( Page::class, 'id', 'id' )->withTrashed();
    }


    /**
     * Relation to the language variant of the page for writing its content, including trashed variants.
     *
     * @return BelongsTo<PageVariant, $this>
     */
    public function variant() : BelongsTo
    {
        return $this->belongsTo( PageVariant::class, 'variant_id', 'id' )->withTrashed();
    }


    /**
     * Relation to the languages of all variants of the page including the trashed ones.
     *
     * @return HasMany<PageVariant, $this>
     */
    public function languages() : HasMany
    {
        return $this->hasMany( PageVariant::class, 'page_id' )->withTrashed()
            ->select( 'id', 'tenant_id', 'page_id', 'lang', 'deleted_at' );
    }


    /**
     * Relation to the published language variants of the page.
     *
     * Contains enabled variants which aren't in the trash, ordered by language.
     *
     * @return HasMany<PageVariant, $this>
     */
    public function variants() : HasMany
    {
        return $this->hasMany( PageVariant::class, 'page_id' )
            ->select( 'id', 'tenant_id', 'page_id', 'lang', 'domain', 'path', 'to', 'status' )
            ->whereIn( 'status', [1, 2] )
            ->orderBy( 'lang' );
    }


    /**
     * Returns the page of the page view for writing the tree columns.
     *
     * The page is built from the loaded values without querying the database, contains
     * the changed tree values of the page view and is available as "page" relation.
     *
     * @return Page Page with the stored and the changed tree values
     */
    public function toPage() : Page
    {
        $cols = array_flip( PageBuilder::PAGE_COLUMNS );
        $page = ( new Page() )->newFromBuilder( array_intersect_key( $this->getRawOriginal(), $cols ) );
        $page->setRawAttributes( array_replace( $page->getAttributes(), array_intersect_key( $this->getDirty(), $cols ) ) );

        $this->setRelation( 'page', $page );
        return $page;
    }


    /**
     * Returns the variant of the page view for writing the language specific columns.
     *
     * The variant is built from the loaded values without querying the database, contains
     * the changed variant values of the page view and is available as "variant" relation.
     *
     * @return PageVariant Variant with the stored and the changed values
     * @throws \LogicException If the page view wasn't loaded with its variant ID
     */
    public function toVariant() : PageVariant
    {
        $original = $this->getRawOriginal();

        // without the variant ID, an update wouldn't change any row
        if( empty( $original['variant_id'] ) ) {
            throw new \LogicException( sprintf( 'Page "%s" must be loaded with its variant ID to be saved', $this->getKey() ) );
        }

        $variant = ( new PageVariant() )->newFromBuilder( PageBuilder::variantValues( $original, true )
            + ['page_id' => $this->getKey(), 'tenant_id' => $original['tenant_id'] ?? null] );
        $variant->setRawAttributes( array_replace( $variant->getAttributes(), PageBuilder::variantValues( $this->getDirty(), true ) ) );

        $this->setRelation( 'variant', $variant );
        return $variant;
    }


    /**
     * Takes over the stored values of the page and its variant.
     *
     * The values are marked as unchanged and the models are available as "page"
     * and "variant" relations afterwards. The variant overwrites the timestamps.
     *
     * @param Page|PageVariant ...$models Saved page and/or variant of the page view
     * @return static Same page view with the stored values
     */
    public function synced( Page|PageVariant ...$models ) : static
    {
        $values = [];

        foreach( $models as $model )
        {
            $attributes = $model->getAttributes();

            if( $model instanceof Page ) {
                $values = array_replace( $values, array_intersect_key( $attributes, array_flip( PageBuilder::PAGE_COLUMNS ) ) );
                $this->setRelation( 'page', $model );
            } else {
                $values = array_replace( $values, PageBuilder::viewValues( $attributes ) );
                $this->setRelation( 'variant', $model );
            }
        }

        $this->setRawAttributes( array_replace( $this->getAttributes(), $values ) );
        $this->syncOriginalAttributes( array_keys( $values ) );

        return $this;
    }


    /**
     * Returns the page route of the given version, falling back to the current values.
     *
     * @param Version $version Page version
     * @return array{path: string, domain: string} Path and domain of the page
     */
    public function route( Version $version ) : array
    {
        return [
            'path' => (string) ( $version->data->path ?? $this->path ),
            'domain' => (string) ( $version->data->domain ?? $this->domain ),
        ];
    }


    /**
     * Tests if the page variant is the source variant of the page.
     *
     * @return bool TRUE if it's the source variant or the languages are unknown
     */
    public function isSourceVariant(): bool
    {
        $lang = $this->attributes['lang'] ?? null;
        $source = $this->attributes['source'] ?? null;

        return $lang === null || $source === null || $lang === $source;
    }


    /**
     * The page view is read-only, use the Page model instead.
     *
     * @throws \LogicException Always
     */
    public function delete() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page view is read-only, use the Page model instead.
     *
     * @throws \LogicException Always
     */
    public function forceDelete() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page view is read-only, prune pages using the Page model instead.
     *
     * @throws \LogicException Always
     */
    public function prune() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page view is read-only, use the Page model instead.
     *
     * @throws \LogicException Always
     */
    public function restore() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page view is read-only, use the Page and PageVariant models instead.
     *
     * @param array<string, mixed> $options Unused
     * @throws \LogicException Always
     */
    public function save( array $options = [] ) : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * Restricts queries reloading the model to the variant of the model.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function setKeysForSelectQuery( $query )
    {
        parent::setKeysForSelectQuery( $query );
        return $this->useVariant( $query );
    }


    /**
     * Uses the variant of the model in the query if known.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function useVariant( $query )
    {
        if( $query instanceof PageQuery && ( $id = $this->original['variant_id'] ?? $this->attributes['variant_id'] ?? null ) ) {
            $query->variant( (string) $id );
        }

        return $query;
    }


    /**
     * Returns page-specific publication values.
     *
     * @return array<string, mixed>
     */
    protected function values( Version $version ) : array
    {
        return [
            'content' => $version->aux->content ?? [],
            'config' => $version->aux->config ?? new \stdClass(),
            'meta' => $version->aux->meta ?? new \stdClass(),
        ];
    }
}
