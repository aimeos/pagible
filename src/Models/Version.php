<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
}
