<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Kalnoy\Nestedset\NodeTrait;
use Kalnoy\Nestedset\AncestorsRelation;
use Kalnoy\Nestedset\DescendantsRelation;


/**
 * Page model
 */
class Page extends Model
{
    use SoftDeletes;
    use Prunable;
    use NodeTrait;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array
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
     * @var array
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
     * @var array
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
        'config',
        'content',
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
     * Get the shared element for the page.
     *
     * @return BelongsToMany Eloquent relationship to the elements attached to the page
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_page_element' );
    }


    /**
     * Get all files referenced by the versioned data.
     *
     * @return BelongsToMany Eloquent relationship to the files
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_page_file' );
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
     * Get the connection name for the model.
     *
     * @return string Name of the database connection to use
     */
    public function getConnectionName() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Maps the elements by ID automatically.
     *
     * @return Collection List elements with ID as keys and element models as values
     */
    public function getElementsAttribute() : Collection
    {
        $this->relationLoaded( 'elements' ) ?: $this->load( 'elements' );
        return $this->getRelation( 'elements' )->pluck( null, 'id' );
    }


    /**
     * Maps the files by ID automatically.
     *
     * @return Collection List files with ID as keys and file models as values
     */
    public function getFilesAttribute() : Collection
    {
        $this->relationLoaded( 'files' ) ?: $this->load( 'files' );
        return $this->getRelation( 'files' )->pluck( null, 'id' );
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
     * Get the page's latest head/meta data.
     *
     * @return MorphOne Eloquent relationship to the latest version of the page
     */
    public function latest() : MorphOne
    {
        return $this->morphOne( Version::class, 'versionable' )->latestOfMany();
    }


    /**
     * Get the navigation for the page.
     *
     * @return \Kalnoy\Nestedset\Collection Collection of ancestor pages
     */
    public function nav() : \Kalnoy\Nestedset\Collection
    {
        $root = $this->ancestors->first() ?: $this;
        return $root?->subtree?->toTree() ?: new \Kalnoy\Nestedset\Collection();
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder Eloquent query builder for pruning models
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
        DB::connection( $this->getConnectionName() )->transaction( function() use ( $version ) {

            $this->files()->sync( $version->files ?? [] );
            $this->elements()->sync( $version->elements ?? [] );

            $this->fill( (array) $version->data + (array) $version->aux );
            $this->editor = $version->editor;
            $this->save();

            $version->published = true;
            $version->save();

        }, 3 );

        return $this;
    }


    /**
     * Get the page's published head/meta data.
     *
     * @return HasOne Eloquent relationship to the last published version of the page
     */
    public function published() : HasOne
    {
        return $this->hasOne( Version::class, 'versionable_id' )
            ->where( 'versionable_type', Page::class )
            ->where( 'published', true )
            ->orderBy( 'id', 'desc' )
            ->take( 1 );
    }


    /**
     * Removes all versions of the page except the latest versions.
     *
     * @return self The current instance for method chaining
     */
    public function removeVersions() : self
    {
        $num = config( 'cms.versions', 10 );

        // MySQL doesn't support offsets for DELETE
        $ids = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', Page::class )
            ->orderBy( 'id', 'desc' )
            ->skip( $num )
            ->take( 10 )
            ->pluck( 'id' );

        if( !$ids->isEmpty() ) {
            Version::whereIn( 'id', $ids )->forceDelete();
        }

        return $this;
    }


    /**
     * Get query for the complete sub-tree up to three levels.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function subtree() : DescendantsRelation
    {
        // restrict max. depth to three levels for performance reasons
        $builder = $this->newScopedQuery()->withDepth()->having( 'depth', '<=', ( $this->depth ?? 0 ) + 3 );

        if( !\Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->where( $this->qualifyColumn( 'status' ), '>', 0 );
        }

        return new DescendantsRelation( $builder, $this );
    }


    /**
     * Get all of the page's versions.
     *
     * @return MorphMany Eloquent relationship to the versions of the page
     */
    public function versions() : MorphMany
    {
        return $this->morphMany( Version::class, 'versionable' );
    }


    /**
     * Apply default ordering to all queries
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('treeorder', function( Builder $builder ) {
            $builder->defaultOrder();
        });
    }


    /**
     * Interact with the "cache" property.
     *
     * @return Attribute Eloquent attribute for the "cache" property
     */
    protected function cache(): Attribute
    {
        return Attribute::make(
            set: fn($value) => $value === null ? 5 : (int) $value,
        );
    }


    /**
     * Interact with the "config" property.
     *
     * @return Attribute Eloquent attribute for the "config" property
     */
    protected function config(): Attribute
    {
        return Attribute::make(
            set: fn($value) => json_encode( $value ?? new \stdClass() ),
        );
    }


    /**
     * Interact with the "content" property.
     *
     * @return Attribute Eloquent attribute for the "content" property
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            set: fn($value) => json_encode( $value ?? [] ),
        );
    }


    /**
     * Interact with the "domain" property.
     *
     * @return Attribute Eloquent attribute for the "domain" property
     */
    protected function domain(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Interact with the "name" property.
     *
     * @return Attribute Eloquent attribute for the "name" property
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Interact with the "meta" property.
     *
     * @return Attribute Eloquent attribute for the "meta" property
     */
    protected function meta(): Attribute
    {
        return Attribute::make(
            set: fn($value) => json_encode( $value ?? new \stdClass() ),
        );
    }


    /**
     * Interact with the "path" property.
     *
     * @return Attribute Eloquent attribute for the "path" property
     */
    protected function path(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Prepare the model for pruning.
     */
    protected function pruning() : void
    {
        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', Page::class )
            ->delete();
    }


    /**
     * Interact with the "status" property.
     *
     * @return Attribute Eloquent attribute for the "status" property
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (int) $value,
        );
    }


    /**
     * Interact with the "tag" property.
     *
     * @return Attribute Eloquent attribute for the "tag" property
     */
    protected function tag(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Interact with the "theme" property.
     *
     * @return Attribute Eloquent attribute for the "theme" property
     */
    protected function theme(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Interact with the "to" property.
     *
     * @return Attribute Eloquent attribute for the "to" property
     */
    protected function to(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }


    /**
     * Interact with the "type" property.
     *
     * @return Attribute Eloquent attribute for the "type" property
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            set: fn($value) => (string) $value,
        );
    }
}
