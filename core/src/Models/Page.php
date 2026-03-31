<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Nestedset\NodeTrait;
use Aimeos\Nestedset\AncestorsRelation;
use Aimeos\Nestedset\DescendantsRelation;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;


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
 * @property int $_lft
 * @property int $_rgt
 * @property int $depth
 * @property string|null $parent_id
 * @property string|null $latest_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Aimeos\Nestedset\Collection<int, Nav>|null $subtree
 * @property-read Collection<int, Page> $ancestors
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Page extends Base
{
    use HasUuids;
    use NodeTrait;
    use SoftDeletes;
    use Prunable;
    use Tenancy;
    use Searchable {
        NodeTrait::usesSoftDelete insteadof Searchable;
    }


    /** @var Collection<int, Page>|null */
    private ?Collection $cachedAncestorsAndSelf = null;


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
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
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
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_pages';


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

        $config = config( 'cms.schemas.content', [] );

        foreach( collect( (array) $this->content )->merge( $this->elements ) as $el )
        {
            $fields = (array) ( $config[@$el->type]['fields'] ?? [] );

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
            ->select( 'id', 'parent_id', '_lft', '_rgt', 'depth', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config' )
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
            ->select( 'id', 'parent_id', '_lft', '_rgt', 'depth', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config' )
            ->setModel( new Nav() )
            ->defaultOrder();
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
     * Enforce JSON columns to return object.
     *
     * @param string $key Attribute name
     * @return mixed Attribute value
     */
    public function getAttribute( $key )
    {
        $value = parent::getAttribute( $key );
        return is_null( $value ) && in_array( $key, ['meta', 'config', 'content'] ) ? new \stdClass() : $value;
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
     * Tests if node has children.
     *
     * @return bool TRUE if node has children, FALSE if not
     */
    public function getHasAttribute() : bool
    {
        return $this->_rgt > $this->_lft + 1;
    }


    /**
     * Returns the cache key for the page.
     *
     * @param Page|string $page Page object or URL path
     * @param string $domain Domain name
     * @return string Cache key
     */
    public static function key( $page, string $domain = '' ) : string
    {
        if( $page instanceof Page ) {
            return md5( \Aimeos\Cms\Tenancy::value() . '/' . $page->domain . '/' . $page->path );
        }

        return md5( \Aimeos\Cms\Tenancy::value() . '/' . $domain . '/' . $page );
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
     * Get the navigation for the page.
     *
     * @param int $level Starting level for the navigation (default: 0 for root page)
     * @return \Aimeos\Nestedset\Collection Collection of ancestor pages
     */
    public function nav( $level = 0 ) : \Aimeos\Nestedset\Collection
    {
        return collect( $this->ancestors )->push( $this )
            ->skip( $level )->first()
            ?->subtree?->toTree()
            ?? new \Aimeos\Nestedset\Collection();
    }


    /**
     * Relation to the parent.
     *
     * @return BelongsTo<Nav, $this>
     */
    public function parent() : BelongsTo
    {
        return $this->belongsTo( Nav::class, $this->getParentIdName() )
            ->select( 'id', 'parent_id', '_lft', '_rgt', 'depth', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config' )
            ->setModel( new Nav() );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning models
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) );
    }


    /**
     * Publish the given version of the page.
     *
     * @param Version $version Version to publish
     * @return self Returns the page object for method chaining
     */
    public function publish( Version $version ) : self
    {
        $this->files()->sync( $version->files ?? [] );
        $this->elements()->sync( $version->elements ?? [] );

        $this->fill( (array) $version->data );
        $this->content = $version->aux->content ?? [];
        $this->config = $version->aux->config ?? new \stdClass();
        $this->meta = $version->aux->meta ?? new \stdClass();
        $this->editor = $version->editor;
        $this->save();

        $version->published = true;
        $version->save();

        Cache::forget( static::key( $this ) );

        return $this;
    }


    /**
     * Determine if the page should be searchable.
     *
     * @return bool TRUE if the page should be searchable, FALSE if not
     */
    public function shouldBeSearchable() : bool
    {
        return $this->status > 0;
    }


    /**
     * Get query for the complete sub-tree up to three levels.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function subtree() : DescendantsRelation
    {
        // restrict maximum depth to three levels for performance reasons
        $maxDepth = ( $this->depth ?? 0 ) + config( 'cms.navdepth', 2 );

        $isEditor = \Aimeos\Cms\Permission::can( 'page:view', Auth::user() );

        $builder = $this->newScopedQuery()
            ->select( 'id', 'parent_id', '_lft', '_rgt', 'depth', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config' )
            ->whereIn( 'depth', range( 0, $maxDepth ) )
            ->defaultOrder()
            ->setModel(new Nav());

        if( $isEditor ) {
            $builder->with( 'latest' );
        }

        if( !$isEditor )
        {
            $table = $this->getTable();

            $builder->whereNotExists( function( $query ) use ( $table ) {
                $query->select( DB::raw( 1 ) )
                    ->from( $table . ' as disabled' )
                    ->where( 'disabled.tenant_id', '=', \Aimeos\Cms\Tenancy::value() )
                    ->where( 'disabled.status', 0 )
                    ->whereNull( 'disabled.deleted_at' )
                    ->whereColumn( 'disabled._lft', '<=', $table . '._lft' )
                    ->whereColumn( 'disabled._rgt', '>=', $table . '._rgt' );
            });
        }

        return new DescendantsRelation( $builder, $this );
    }


    /**
     * Returns the searchable data for the page.
     *
     * @return array<string, string>
     */
    public function toSearchableArray(): array
    {
        $attrs = ['path', 'to', 'tag', 'name', 'title', 'meta', 'content', 'deleted_at', 'latest_id'];

        // bulk index + changed content check for performance reasons
        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        $draft = '';

        if( $version = $this->latest )
        {
            $data = $version->data ?? new \stdClass();
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

        return ['content' => $content, 'draft' => $draft, 'domain' => $this->domain ?? '', 'lang' => $this->lang ?? ''];
    }


    /**
     * Prepare the model for pruning.
     */
    protected function pruning() : void
    {
        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', static::class )
            ->delete();
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
            set: fn( $value ) => json_encode( $value ?? new \stdClass() ),
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
            set: fn( $value ) => json_encode( $value ?? new \stdClass() ),
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
     * Interact with the "related_id" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "related_id" property
     */
    protected function relatedId(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => !empty( $value) ? (string) $value : null,
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
}
