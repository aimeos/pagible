<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Query\PageQuery;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;


/**
 * Page model
 *
 * Facade over the page structure (cms_pages) and one of its language variants
 * (cms_page_variants). By default, the variant of the source language is used.
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
 */
class Page extends Base
{
    use NodeTrait;

    public const PERM = 'page';
    protected const REFS = ['files', 'elements'];

    /** @var list<string> Columns required for Page lifecycle operations */
    public const REQUIRED_COLUMNS = [
        'id', 'variant_id', 'tenant_id', 'parent_id', 'source', 'lang', 'path', 'domain', 'editor', 'latest_id', 'deleted_at',
        NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH,
    ];

    /** @var list<string> Optional columns available for selective Page responses */
    public const RESPONSE_COLUMNS = [
        'name', 'title', 'tag', 'to', 'type', 'theme', 'meta', 'config',
        'content', 'status', 'cache', 'created_at', 'updated_at',
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
    protected $attributes = [
        'tenant_id' => '',
        'tag' => '',
        'lang' => '',
        'path' => '',
        'domain' => '',
        'to' => '',
        'name' => '',
        'title' => '',
        'type' => '',
        'theme' => '',
        'meta' => '{}',
        'config' => '{}',
        'content' => '[]',
        'status' => 0,
        'cache' => 5,
        'editor' => '',
        'hashes' => '{}',
        'stale' => false,
    ];

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
        'status' => 'integer',
        'cache' => 'integer',
        'meta' => 'object',
        'config' => 'object',
        'content' => 'object', // for object access in templates
        'hashes' => 'array',
        'stale' => 'boolean',
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
     * Connections whose schema contains the page variants table.
     *
     * @var array<string, true|null>
     */
    private static array $schema = [];


    /**
     * Boot the model.
     */
    protected static function booted() : void
    {
        static::creating( function( Page $page ) {
            $page->setAttribute( 'variant_id', $page->getAttribute( 'variant_id' ) ?: $page->getKey() );
            $page->setAttribute( 'source', $page->getAttribute( 'source' ) ?: (string) $page->lang );
        } );
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
     * Generates the unique IDs including the ID of the source variant, which is the same as the page ID.
     */
    public function setUniqueIds() : void
    {
        parent::setUniqueIds();

        if( !$this->getAttribute( 'variant_id' ) ) {
            $this->setAttribute( 'variant_id', $this->getKey() );
        }
    }


    /**
     * Creates a new Eloquent query builder for the model.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return PageQuery<self>
     */
    public function newEloquentBuilder( $query ) : PageQuery
    {
        // migrations running before the page variants exist use the plain page table
        if( $query instanceof PageBuilder && ( self::$schema[$this->getConnectionName()] ??= $this->getConnection()->getSchemaBuilder()->hasTable( 'cms_page_variants' ) ?: null ) ) {
            $query->variants();
        }

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
     * Resets the cached schema state, e.g. while migrations are running.
     */
    public static function resetSchema() : void
    {
        self::$schema = [];
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

        return new AncestorsRelation( $builder, $this );
    }


    /**
     * Relation to children.
     *
     * @return HasMany<Nav, $this>
     */
    public function children() : HasMany
    {
        return $this->hasMany( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )
            ->setModel( new Nav() )
            ->defaultOrder();
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
        $variants = PageVariant::withTrashed()->whereIn( 'page_id', array_keys( $pages ) )
            ->orderBy( 'lang' )->get( ['id', 'page_id', 'lang', 'stale', 'latest_id', 'deleted_at'] );

        $published = Version::whereIn( 'id', $variants->pluck( 'latest_id' )->filter()->unique()->values()->all() )
            ->pluck( 'published', 'id' );

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
                'published' => (bool) ( $published[$variant->latest_id] ?? false ),
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
        return $this->belongsTo( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )->setModel( new Nav() );
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
     * Applies a lifecycle action to each locked page to keep the nested set consistent.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Base> $items Pages
     * @param 'dropped'|'purged'|'restored' $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    public static function lifecycle( \Illuminate\Database\Eloquent\Collection $items, string $action, string $editor ) : void
    {
        foreach( $items as $item )
        {
            if( $action === 'purged' ) {
                $item->forceDelete();
                continue;
            }

            $item->editor = $editor;
            $action === 'dropped' ? $item->delete() : $item->restore();
        }
    }


    /**
     * Positions the page relative to a sibling or parent.
     */
    public function position( ?string $beforeId = null, ?string $parentId = null ) : void
    {
        $columns = ['id', 'tenant_id', 'parent_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH];

        if( $beforeId !== null ) {
            $this->beforeNode( static::withTrashed()->select( $columns )->findOrFail( $beforeId ) );
        } elseif( $parentId !== null ) {
            $this->appendToNode( static::withTrashed()->select( $columns )->findOrFail( $parentId ) );
        } elseif( $this->exists ) {
            $this->makeRoot();
        }
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
        $tenant = (string) $this->tenant_id;
        $ids = PageVariant::withoutTenancy()->withTrashed()
            ->where( 'tenant_id', $tenant )
            ->whereIn( 'page_id', fn( $query ) => $query->select( 'id' )->from( 'cms_pages' )
                ->where( 'tenant_id', $tenant )
                ->where( NestedSet::LFT, '>=', $this->getLft() )
                ->where( NestedSet::RGT, '<=', $this->getRgt() )
            )
            ->pluck( 'id' )->all();

        \Aimeos\Cms\Tenancy::run( $tenant, fn() => \Aimeos\Cms\Scout::unindex( static::class, $ids ) );

        parent::pruning();
    }


    /**
     * Get query for the complete sub-tree up to three levels.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function subtree() : DescendantsRelation
    {
        $table = $this->getTable();
        $lft = $this->getLftName();
        $rgt = $this->getRgtName();
        $depth = $this->getDepthName();

        // restrict maximum depth to three levels for performance reasons
        $maxDepth = ( $this->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 );

        $builder = $this->newScopedQuery()
            ->select( Nav::SELECT_COLUMNS )
            ->whereIn( $depth, range( 0, $maxDepth ) )
            ->whereNotExists( function( $query ) use ( $table, $lft, $rgt ) {
                $query->select( DB::raw( 1 ) )
                    ->from( $table . ' as disabled' )
                    ->join( 'cms_page_variants as disabled_variant', 'disabled_variant.page_id', '=', 'disabled.id' )
                    ->whereColumn( 'disabled_variant.lang', "$table.lang" )
                    ->where( 'disabled.tenant_id', '=', \Aimeos\Cms\Tenancy::value() )
                    ->where( 'disabled_variant.status', 0 )
                    ->whereNull( 'disabled.deleted_at' )
                    ->whereColumn( "disabled.$lft", '<=', "$table.$lft" )
                    ->whereColumn( "disabled.$rgt", '>=', "$table.$rgt" );
            })
            ->defaultOrder();

        if( !$this->isSourceVariant() && $builder instanceof PageQuery ) {
            $builder->language( $this->lang );
        }

        if( \Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return new DescendantsRelation( $builder->setModel( new Nav() ), $this );
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
        ];
    }


    /**
     * Restricts queries saving the model to the variant of the model.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    protected function setKeysForSaveQuery( $query )
    {
        parent::setKeysForSaveQuery( $query );
        return $this->useVariant( $query );
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
     * Don't fire model events for each descendant for performance reasons.
     *
     * @return bool FALSE to disable firing events for descendants
     */
    protected function shouldFireDescendantEvents(): bool
    {
        return false;
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
        ] );
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
        return Attribute::make(
            set: fn( $value ) => (string) $value,
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
