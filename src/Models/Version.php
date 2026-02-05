<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Collection;


/**
 * Version model
 */
class Version extends Model
{
    use HasUuids;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'tenant_id' => '',
        'lang' => null,
        'data' => '{}',
        'aux' => '{}',
        'publish_at' => null,
        'published' => false,
        'editor' => '',
    ];

    /**
     * The automatic casts for the attributes.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'object',
        'aux' => 'object',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'publish_at',
        'editor',
        'lang',
        'data',
        'aux',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_versions';


    /**
     * Get the shared element attached to the version.
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_version_element' );
    }


    /**
     * Get all files referenced by the versioned data.
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_version_file' );
    }


    /**
     * Get a fresh timestamp for the model.
     *
     * @return \Illuminate\Support\Carbon
     */
    public function freshTimestamp()
    {
        return Date::now();
    }


    /**
     * Get the connection name for the model.
     */
    public function getConnectionName()
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
     * Disables using the updated_at column.
     * Versions are never updated, each one is created as a new entry.
     */
    public function getUpdatedAtColumn()
    {
        return null;
    }


    /**
     * Get the parent versionable model (page, file or element).
     */
    public function versionable() : MorphTo
    {
        return $this->morphTo();
    }


    /**
     * Returns the list of changed attributes.
     * Required to return the correct boolean value if the "published" property
     * is stored as integer in the database.
     *
     * @return array<string, mixed> List of changed attributes
     */
    public function getDirty()
    {
        $dirty = [];

        foreach( $this->getAttributes() as $key => $value )
        {
            if( $key === 'published' )
            {
                if( (bool)$value !== (bool)$this->original[$key] ) {
                    $dirty[$key] = $value;
                }

                continue;
            }

            if( !$this->originalIsEquivalent( $key ) ) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }
}
