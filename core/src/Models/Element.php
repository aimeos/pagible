<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Casts\LazyObject;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

/**
 * Element model
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $type
 * @property string|null $lang
 * @property string $name
 * @property \stdClass $data
 * @property string $editor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $latest_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Element extends Base
{
    use HasUuids;
    use SoftDeletes;
    use Searchable;
    use Prunable;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
        'type' => '',
        'lang' => null,
        'name' => '',
        'data' => '{}',
        'editor' => '',
    ];

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => LazyObject::class,
        'name' => 'string',
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
        'data',
        'type',
        'lang',
        'name',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_elements';


    /**
     * Returns the text content of the element.
     *
     * @return string Text content
     */
    public function __toString() : string
    {
        $content = ( $this->name ?? '' ) . "\n";
        $config = config( 'cms.schemas.content', [] );
        $fields = (array) ( $config[@$this->data->type]['fields'] ?? [] );

        foreach( (array) ( $this->data->data ?? [] ) as $name => $value )
        {
            if( is_string( $value ) && isset( $fields[$name] )
                && ( $fields[$name]['searchable'] ?? true )
                && in_array( $fields[$name]['type'], ['markdown', 'plaintext', 'string', 'text'] )
            ) {
                $content .= $value . "\n";
            }
        }

        return trim( $content );
    }


    /**
     * Get the pages the element is referenced by.
     *
     * @return BelongsToMany<Page, $this> Eloquent relationship to the pages
     */
    public function bypages() : BelongsToMany
    {
        return $this->belongsToMany( Page::class, 'cms_page_element' )
            ->select('id', 'path', 'name' );
    }


    /**
     * Get the versions the element is referenced by.
     *
     * @return BelongsToMany<Version, $this> Eloquent relationship to the versions referencing the element
     */
    public function byversions() : BelongsToMany
    {
        return $this->belongsToMany( Version::class, 'cms_version_element' )
            ->select('id', 'versionable_id', 'versionable_type', 'published', 'publish_at' );
    }


    /**
     * Get the files referencedd by the element.
     *
     * @return BelongsToMany<File, $this> Eloquent relationship to the files
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_element_file' );
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
        return is_null( $value ) && $key === 'data' ? new \stdClass() : $value;
    }


    /**
     * Maps the files by ID automatically.
     *
     * @return Collection<string, File> List files with ID as keys and file models as values
     */
    public function getFilesAttribute() : Collection
    {
        $files = $this->relationLoaded( 'files' )
            ? $this->getRelation( 'files' )
            : $this->load( 'files' )->getRelation( 'files' );

        return $files->pluck( null, 'id' );
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
     * Publish the given version of the element.
     *
     * @param Version $version Version to publish
     * @return self Returns the element instance
     */
    public function publish( Version $version ) : self
    {
        $this->files()->sync( $version->files ?? [] );

        $this->fill( (array) $version->data );
        $this->editor = $version->editor;
        $this->lang = $version->lang;
        $this->save();

        $version->published = true;
        $version->save();

        return $this;
    }


    /**
     * Returns the searchable data for the element.
     *
     * @return array<string, string>
     */
    public function toSearchableArray(): array
    {
        $attrs = ['name', 'type', 'data', 'deleted_at', 'latest_id'];

        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        return [
            'content' => $this->trashed() ? '' : mb_strtolower( (string) $this ),
            'draft' => mb_strtolower( (string) $this->latest ),
        ];
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


}
