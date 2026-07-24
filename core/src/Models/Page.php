<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Access;
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
 * @property string $id
 * @property string|null $related_id
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
 * @property-read Collection<int, Page> $ancestors
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageAccess> $access
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Page extends Base
{
    use NodeTrait;


    /** @var list<string> Columns required for Page lifecycle operations */
    public const REQUIRED_COLUMNS = [
        'id', 'tenant_id', 'parent_id', 'path', 'domain', 'editor', 'latest_id', 'deleted_at',
        NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH,
    ];

    /** @var list<string> Optional columns available for selective Page responses */
    public const RESPONSE_COLUMNS = [
        'related_id', 'name', 'title', 'tag', 'lang', 'to', 'type', 'theme', 'meta', 'config',
        'content', 'status', 'cache', 'created_at', 'updated_at',
    ];

    /** @var list<string> Columns needed for memory-efficient Page queries */
    public const SELECT_COLUMNS = [
        'id', 'tenant_id', 'parent_id', 'related_id', 'path', 'domain', 'name', 'title',
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
        'related_id' => null,
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
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'related_id',
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
            ->select( 'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'type', 'path', 'domain', 'lang', 'to', 'status', 'latest_id', $lft, $rgt, $depth )
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
        $content = ( $this->tag ?? '' ) . "\n"
            . ( $this->name ?? '' ) . "\n"
            . ( $this->title ?? '' ) . "\n"
            . ( $this->meta->{'meta-tags'}->data->description ?? '' ) . "\n";

        $config = \Aimeos\Cms\Schema::schemas( section: 'content' );

        foreach( collect( (array) $this->content )->merge( $this->elements ) as $el )
        {
            $fields = (array) ( $config[$el->type ?? '']['fields'] ?? [] );

            if( empty( $fields ) ) {
                continue;
            }

            foreach( (array) ( $el->data ?? [] ) as $name => $value )
            {
                if( is_string( $value ) && isset( $fields[$name] )
                    && ( $fields[$name]['searchable'] ?? true )
                    && in_array( $fields[$name]['type'], ['markdown', 'plaintext', 'string', 'text'] )
                ) {
                    $content .= $value . "\n";
                }
            }
        }

        return trim( $content );
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
        return $this->belongsToMany( Element::class, 'cms_page_element', 'page_id' );
    }


    /**
     * Get all files referenced by the versioned data.
     *
     * @return BelongsToMany<File, $this> Eloquent relationship to the files
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_page_file', 'page_id' );
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
            ->select(
                'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status',
                'config', 'latest_id', $this->getDepthName(), $this->getLftName(), $this->getRgtName()
            )->setModel( new Nav() );
    }


    /**
     * Limits a query to pages visible to the frontend user.
     *
     * @param Builder<static> $query
     */
    public function scopeAccess( Builder $query, ?Authenticatable $user ) : void
    {
        if( !$user || !\Aimeos\Cms\Tenancy::allows( $user, \Aimeos\Cms\Tenancy::value() ) ) {
            $query->wherePublic();
            return;
        }

        $values = app( Access::class )->allowed( $user );

        $query->where( function( Builder $query ) use ( $values ) {
            $query->whereDoesntHave( 'access' )->orWhereHas( 'access', function( Builder $query ) use ( $values ) {
                $query->where( function( Builder $query ) use ( $values ) {
                    $query->where( 'value', '' );

                    if( $values ) {
                        $query->orWhereIn( 'value', $values );
                    }
                } );
            } );
        } );
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
            ->select( 'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config', 'latest_id', $lft, $rgt, $depth )
            ->whereIn( $depth, range( 0, $maxDepth ) )
            ->whereNotExists( function( $query ) use ( $table, $lft, $rgt ) {
                $query->select( DB::raw( 1 ) )
                    ->from( $table . ' as disabled' )
                    ->where( 'disabled.tenant_id', '=', \Aimeos\Cms\Tenancy::value() )
                    ->where( 'disabled.status', 0 )
                    ->whereNull( 'disabled.deleted_at' )
                    ->whereColumn( "disabled.$lft", '<=', "$table.$lft" )
                    ->whereColumn( "disabled.$rgt", '>=', "$table.$rgt" );
            })
            ->defaultOrder();

        if( \Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return new DescendantsRelation( $builder->setModel( new Nav() ), $this );
    }


    /**
     * Returns the searchable data for the page.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $attrs = ['domain', 'lang', 'path', 'to', 'tag', 'name', 'title', 'meta', 'content', 'deleted_at', 'latest_id'];

        // bulk index + changed content check for performance reasons
        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        $draft = '';

        if( $version = $this->latest )
        {
            $data = $version->data;
            $draft = mb_strtolower( trim(
                ( $data->path ?? '' ) . "\n"
                . ( $data->to ?? '' ) . "\n"
                . (string) $version
            ) );
        }

        $content = '';

        if( !$this->trashed() )
        {
            $content = mb_strtolower( trim(
                $this->path . "\n"
                . $this->to . "\n"
                . (string) $this
            ) );
        }

        $version = $this->latest;
        $data = $version?->data;

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
            'restricted' => $this->relationLoaded( 'access' )
                ? $this->getRelation( 'access' )->isNotEmpty()
                : $this->access()->exists(),
        ];
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
        return $query->select( self::SELECT_COLUMNS )->withCount( 'access' )->with( [
            'elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            'latest' => fn( $q ) => $q->select( [...Version::SELECT_COLUMNS, 'aux'] ),
            'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
        ] );
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
