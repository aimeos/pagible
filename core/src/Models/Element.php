<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Referenced;
use Aimeos\Cms\ContentType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Aimeos\Cms\Translation\Texts;

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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PageView> $bypages
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 */
class Element extends Base
{
    use Referenced;

    public const PERM = ContentType::Element->value;
    protected const REFS = ['files'];

    /** @var list<string> Columns for eager-loading element relations */
    public const SELECT_COLUMNS = [
        'cms_elements.id', 'cms_elements.tenant_id', 'cms_elements.latest_id', 'type', 'name', 'data',
    ];


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
        'data' => 'object',
        'name' => 'string',
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
     * The attributes that are returned by toArray()
     *
     * @var list<string>
     */
    protected $visible = [
        'type',
        'lang',
        'name',
        'data',
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
        return trim( implode( "\n", [
            $this->name ?? '',
            ...Texts::searchable( [$this->data] ),
        ] ) );
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
        return static::expired()->select( 'id', 'tenant_id', 'deleted_at' );
    }


    /**
     * Returns the searchable data for the element.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return $this->searchableVersion( ['name', 'type', 'data', 'deleted_at', 'latest_id'], 'type' );
    }


    /**
     * Interact with the "data" property.
     *
     * @return Attribute<mixed, mixed> Eloquent attribute for the "data" property
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            set: fn( $value ) => json_encode( $value ?? new \stdClass() )
        );
    }


    /**
     * Returns element-specific publication values.
     *
     * @return array<string, mixed>
     */
    protected function values( Version $version ) : array
    {
        return ['lang' => $version->lang];
    }


}
