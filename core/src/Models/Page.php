<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Query\PageQuery;
use Aimeos\Cms\Query\SubtreeRelation;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Validation;
use Aimeos\Nestedset\NodeTrait;
use Aimeos\Nestedset\NestedSet;
use Aimeos\Nestedset\AncestorsRelation;
use Aimeos\Nestedset\DescendantsRelation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;


/**
 * Page model
 *
 * Facade over the page structure (cms_pages) and one of its language variants
 * (cms_page_variants), read from the cms_page_view view joining both tables.
 * By default, the variant of the source language is used.
 * The model is read-only, Resource writes the tree columns through PageNode and
 * the language specific columns through PageVariant.
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
 * @property-read Collection<int, Page> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageAccess> $access
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static PageQuery<static> language(?string $lang, bool $trashed = false)
 * @method static PageQuery<static> variant(string $id)
 * @method static PageQuery<static> allVariants(bool $trashed = false)
 * @method static PageQuery<static> fallback(string $lang, bool $trashed = false)
 * @method static PageQuery<static> visible(string $lang)
 * @method static PageQuery<static> localized(string $lang, bool $editor)
 */
class Page extends Base
{
    use NodeTrait;

    public const PERM = 'page';
    public const READONLY = 'Pages are read-only, write pages using Resource';
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
    protected $casts = [
        'tag' => 'string',
        'lang' => 'string',
        'path' => 'string',
        'domain' => 'string',
        'to' => 'string',
        'name' => 'string',
        'title' => 'string',
        'type' => 'string',
        'theme' => 'string',
        ...PageVariant::CASTS,
    ];

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
    protected $table = 'cms_pages';

    /**
     * Ancestors and self collection cache for performance reasons.
     *
     * @var Collection<int, Page>|null
     */
    protected ?Collection $cachedAncestorsAndSelf = null;


    /**
     * Selects the variants of the pages read from the page view.
     */
    protected static function booted() : void
    {
        static::addGlobalScope( new \Aimeos\Cms\Scopes\Variant() );
    }


    /**
     * Returns a query builder for the full page tree including disabled and trashed pages.
     *
     * @param string|null $id Root page ID or null for all root pages
     * @return \Aimeos\Nestedset\QueryBuilder<Nav> Query builder for further chaining, call ->get()->toTree() to execute
     */
    public static function tree( ?string $id = null ) : \Aimeos\Nestedset\QueryBuilder
    {
        $root = $id ? static::withTrashed()->findOrFail( $id ) : null;
        $maxDepth = ( $root?->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 );

        $lft = NestedSet::LFT;
        $rgt = NestedSet::RGT;
        $depth = NestedSet::DEPTH;

        $builder = Nav::withTrashed()
            ->select( 'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'type', 'path', 'domain', 'lang', 'source', 'to', 'status', 'latest_id', $lft, $rgt, $depth )
            ->orderBy( $lft );

        if( $root ) {
            $builder->where( $lft, '>=', $root->getLft() )
                ->where( $rgt, '<=', $root->getRgt() )
                ->whereIn( $depth, range( (int) $root->getDepth(), $maxDepth ) );
        } else {
            $builder->whereIn( $depth, range( 0, $maxDepth ) );
        }

        return $builder;
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
     * Get query ancestors of the node.
     *
     * @return  AncestorsRelation
     */
    public function ancestors() : AncestorsRelation
    {
        $builder = $this->newScopedQuery()
            ->select( Nav::SELECT_COLUMNS )
            ->setModel( new Nav() )
            ->defaultOrder();

        // ancestors provide the inherited config, so they fall back to the source variant in both modes
        $this->localize( $builder, false );
        return new AncestorsRelation( $builder, $this );
    }


    /**
     * Relation to children.
     *
     * @return HasMany<Nav, $this>
     */
    public function children() : HasMany
    {
        $relation = $this->hasMany( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )
            ->setModel( new Nav() )
            ->defaultOrder();

        $this->localize( $relation->getQuery() );
        return $relation;
    }


    /**
     * Explicit frontend access rules for this page.
     *
     * @return HasMany<PageAccess, $this>
     */
    public function access() : HasMany
    {
        return $this->hasMany( PageAccess::class, 'page_id' );
    }


    /**
     * Returns the canonical immediate frontend access state.
     *
     * @return list<string>|null
     */
    public function accessValues() : ?array
    {
        return PageAccess::values( $this->access );
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
     * Returns whether the page has explicit frontend access rules.
     */
    public function restricted() : bool
    {
        if( $this->relationLoaded( 'access' ) ) {
            return $this->getRelation( 'access' )->isNotEmpty();
        }

        $count = $this->getAttribute( 'access_count' );

        return $count !== null
            ? (int) $count > 0
            : $this->access()->exists();
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
     * Returns ancestors including self (root→self), cached per instance.
     *
     * @return Collection<int, Page>
     */
    public function getAncestorsAndSelfAttribute() : Collection
    {
        return $this->cachedAncestorsAndSelf ??= collect( $this->ancestors )->push( $this );
    }


    /**
     * Returns the number of descendants below this node.
     *
     * Derived from the nested set bounds without a query: the range [lft, rgt] spans the node
     * and all its descendants at two slots each, so the count excludes the node itself. Zero for
     * a leaf, so it still reads as "no children" where a boolean was expected, while a recursive
     * bulk edit can size itself ("apply to N pages") from it. An unsaved node (null bounds) has no
     * descendants - the ?? 0 keeps that case from doing null arithmetic.
     *
     * @return int Number of descendant pages
     */
    public function getHasAttribute() : int
    {
        return intdiv( ( $this->getRgt() ?? 0 ) - ( $this->getLft() ?? 0 ) - 1, 2 );
    }


    /**
     * Get the menu for the page.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function menu() : DescendantsRelation
    {
        return ( $this->ancestors->first() ?? $this )->subtree();
    }


    /**
     * Relation to the parent.
     *
     * @return BelongsTo<Nav, $this>
     */
    public function parent() : BelongsTo
    {
        $relation = $this->belongsTo( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )->setModel( new Nav() );

        // the parent page always exists, so it falls back to the source variant in both modes
        $this->localize( $relation->getQuery(), false );
        return $relation;
    }


    /**
     * Create a new instance of the given model.
     *
     * Relations are eager loaded from a new instance of the query model, so a language
     * set at the query model (e.g. by the JSON:API "lang" filter) is passed to the new
     * instance for localizing the navigation relations.
     *
     * @param array<string, mixed> $attributes
     * @param bool $exists
     * @return static
     */
    public function newInstance( $attributes = [], $exists = false )
    {
        $model = parent::newInstance( $attributes, $exists );
        $lang = $this->attributes['lang'] ?? '';

        if( !$this->exists && !$exists && $lang !== '' && ( $model->attributes['lang'] ?? '' ) === '' ) {
            $model->attributes['lang'] = $lang;
        }

        return $model;
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
     * Limits a query to pages visible to the frontend user.
     *
     * @param Builder<static> $query
     */
    public function scopeAccess( Builder $query, ?Authenticatable $user ) : void
    {
        PageAccess::apply( $query, $user );
    }


    /**
     * Adds frontend access decisions to the page query without loading rule rows.
     *
     * @param Builder<static> $query
     */
    public function scopeWithAccess( Builder $query, ?Authenticatable $user ) : void
    {
        PageAccess::flags( $query, $user );
    }


    /**
     * Limits a query to pages without an explicit frontend access rule.
     *
     * @param Builder<static> $query
     */
    public function scopeWherePublic( Builder $query ) : void
    {
        $query->whereDoesntHave( 'access' );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning models
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()
            ->select( 'id', 'tenant_id', 'parent_id', 'deleted_at', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) );
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
     * Removes the search index entries of all variants in the pruned subtree.
     */
    protected function pruning() : void
    {
        if( \Aimeos\Cms\Scout::usesSearchIndex() )
        {
            $tenant = (string) $this->tenant_id;

            PageVariant::withoutTenancy()->withTrashed()->select( 'id' )
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'page_id', fn( $query ) => $query->select( 'id' )->from( 'cms_pages' )
                    ->where( 'tenant_id', $tenant )
                    ->where( NestedSet::LFT, '>=', $this->getLft() )
                    ->where( NestedSet::RGT, '<=', $this->getRgt() )
                )
                ->chunkById( 500, fn( $variants ) => \Aimeos\Cms\Tenancy::run( $tenant,
                    fn() => \Aimeos\Cms\Scout::unindex( static::class, $variants->pluck( 'id' )->all() ) ) );
        }

        parent::pruning();
    }


    /**
     * Get query for the complete sub-tree up to three levels.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function subtree() : DescendantsRelation
    {
        $depth = $this->getDepthName();

        // restrict maximum depth to three levels for performance reasons
        $maxDepth = ( $this->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 );

        $builder = $this->newScopedQuery()
            ->select( Nav::SELECT_COLUMNS )
            ->whereIn( $depth, range( 0, $maxDepth ) )
            ->defaultOrder();

        // sub-pages of disabled pages and, when hiding untranslated pages, of pages
        // without a variant in that language are pruned by the relation
        $this->localize( $builder );

        if( \Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return new SubtreeRelation( $builder->setModel( new Nav() ), $this );
    }


    /**
     * Tests if pages without a visible variant in the current language are shown in their source language.
     *
     * @return bool TRUE for the "source" fallback, FALSE if such pages are hidden
     */
    public static function fallbackToSource() : bool
    {
        return config( 'cms.translate.fallback', 'hide' ) === 'source';
    }


    /**
     * Uses the variants in the language of this page for a navigation query.
     *
     * Pages without a visible variant in that language are left out or replaced by their
     * source variant depending on the "cms.translate.fallback" setting. Editors see
     * unpublished variants too. Queries of models without a language, e.g. when eager
     * loading relations, keep using the source variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder Page or navigation query
     * @param bool $hide Leave out pages without variant in "hide" mode, FALSE always falls back to the source variant
     * @return bool TRUE if the query has been restricted to the language, FALSE if not
     */
    public function localize( $builder, bool $hide = true ) : bool
    {
        $lang = $this->attributes['lang'] ?? null;

        if( !is_string( $lang ) || $lang === '' || !$builder instanceof PageQuery ) {
            return false;
        }

        $editor = \Aimeos\Cms\Permission::can( 'page:view', Auth::user() );

        // disabled variants are left out for visitors like in the "source" mode
        $hide && !self::fallbackToSource()
            ? $builder->language( $lang )->when( !$editor, fn( $q ) => $q->where( $q->qualifyColumn( 'status' ), '<>', 0 ) )
            : $builder->localized( $lang, $editor );

        return true;
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
     * Returns the key of the search index entry, which is the variant ID.
     *
     * @return mixed Variant ID or the page ID for the first variant
     */
    public function getScoutKey(): mixed
    {
        return $this->getAttribute( 'variant_id' ) ?? $this->getKey();
    }


    /**
     * Returns the facade column of the search index key, each page variant is indexed separately.
     *
     * @return string Column name
     */
    public function getScoutKeyName(): string
    {
        return 'variant_id';
    }


    /**
     * Gets the page variants matching the search index keys.
     *
     * @param \Laravel\Scout\Builder<static> $builder Scout search builder
     * @param array<int, mixed> $ids Variant IDs
     * @return \Illuminate\Database\Eloquent\Builder<static> Query for the page variants
     */
    public function queryScoutModelsByIds( \Laravel\Scout\Builder $builder, array $ids )
    {
        $query = $this->newQuery();
        $query->withTrashed();

        if( !Scout::fallback( $query, $builder ) ) {
            $query->allVariants( true );
        }

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
        $attrs = ['domain', 'lang', 'path', 'to', 'tag', 'name', 'title', 'meta', 'content', 'deleted_at', 'variant_deleted_at', 'latest_id'];

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
            'langs' => Scout::langs( $this, false ),
            'langs_trashed' => Scout::langs( $this, true ),
        ] : [] );
    }


    /**
     * Returns the query for the structure of the page tree.
     *
     * The nested set writes the page tree directly instead of the page facade.
     *
     * @param string|null $table Table name
     * @return \Aimeos\Nestedset\QueryBuilder<PageNode> Query builder including trashed nodes
     */
    public function newNestedSetQuery( ?string $table = null ) : \Aimeos\Nestedset\QueryBuilder
    {
        return ( new PageNode() )->newNestedSetQuery( $table );
    }


    /**
     * The page tree node deletes the descendants.
     */
    protected function deleteDescendants() : void
    {
    }


    /**
     * The page tree node restores the descendants.
     *
     * @param \Carbon\Carbon|null $deletedAt Time the page has been moved into the trash
     */
    protected function restoreDescendants( ?\Carbon\Carbon $deletedAt ) : void
    {
    }


    /**
     * The page facade is read-only, use Resource::removePage() instead.
     *
     * @throws \LogicException Always
     */
    public function delete() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page facade is read-only, use Resource::removePage() instead.
     *
     * @throws \LogicException Always
     */
    public function forceDelete() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * Prunes the trashed page including its descendants and variants.
     *
     * @return bool TRUE on success
     */
    public function prune()
    {
        $this->pruning();

        // the nested set queries are tenant scoped, so they must run in the tenant of the page
        \Aimeos\Cms\Tenancy::run( (string) $this->tenant_id, fn() => \Aimeos\Cms\Resource::removePage( $this ) );

        return true;
    }


    /**
     * The page facade is read-only, use Resource::untrashPage() instead.
     *
     * @throws \LogicException Always
     */
    public function restore() : never
    {
        throw new \LogicException( self::READONLY );
    }


    /**
     * The page facade is read-only, use Resource::insertPage() or Resource::updatePage() instead.
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
     * Modify the query used to retrieve models when making all of the models searchable.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function makeAllSearchableUsing( $query )
    {
        if( $query instanceof PageQuery ) {
            $query->allVariants( true );
        }

        return $query->select( [...self::SELECT_COLUMNS, 'variant_deleted_at'] )->withCount( 'access' )->with( [
            'elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux'] ),
            'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
        ] )->when( Scout::usesExternalSearch(), fn( $q ) => $q->with( 'languages' ) );
    }


    /**
     * Whether one explicit frontend access rule allows the current user.
     *
     * @return Attribute<bool, never>
     */
    protected function accessAllowed() : Attribute
    {
        return Attribute::get( fn( $value ) => (bool) $value );
    }


    /**
     * Whether the page has explicit frontend access rules.
     *
     * @return Attribute<bool, never>
     */
    protected function accessExists() : Attribute
    {
        return Attribute::get( fn( $value ) => (bool) $value );
    }


    /**
     * Interact with the "cache" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "cache" property
     */
    protected function cache(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => $value === null ? 5 : (int) $value,
        );
    }


    /**
     * Interact with the "config" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "config" property
     */
    protected function config(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( Validation::structured( $value, 'config' ) ),
        );
    }


    /**
     * Interact with the "content" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "content" property
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ?? [] ),
        );
    }


    /**
     * Interact with the "domain" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "domain" property
     */
    protected function domain(): Attribute
    {
        // host names are case insensitive and requests use lower case hosts
        return Attribute::make(
            set: fn( $value ) => mb_strtolower( (string) $value ),
        );
    }


    /**
     * Interact with the "name" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "name" property
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "meta" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "meta" property
     */
    protected function meta(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( Validation::structured( $value, 'meta' ) ),
        );
    }


    /**
     * Interact with the "path" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "path" property
     */
    protected function path(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "status" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "status" property
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (int) $value,
        );
    }


    /**
     * Interact with the "tag" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "tag" property
     */
    protected function tag(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "theme" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "theme" property
     */
    protected function theme(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "to" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "to" property
     */
    protected function to(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
    }


    /**
     * Interact with the "type" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "type" property
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => (string) $value,
        );
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
