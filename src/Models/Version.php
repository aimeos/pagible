<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Collection;


/**
 * Version model
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $lang
 * @property \stdClass|null $data
 * @property \stdClass|null $aux
 * @property string|null $publish_at
 * @property bool $published
 * @property string $editor
 * @property string|null $versionable_id
 * @property string|null $versionable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Version extends Model
{
    use HasUuids;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
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
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'object',
        'aux' => 'object',
    ];

    /**
     * The date format with milliseconds used by created_at.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
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
     *
     * @return BelongsToMany<Element, $this>
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_version_element' );
    }


    /**
     * Get all files referenced by the versioned data.
     *
     * @return BelongsToMany<File, $this>
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
     * Disables using the updated_at column.
     * Versions are never updated, each one is created as a new entry.
     */
    public function getUpdatedAtColumn()
    {
        return null;
    }


    /**
     * Get the parent versionable model (page, file or element).
     *
     * @return MorphTo<Model, $this>
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
