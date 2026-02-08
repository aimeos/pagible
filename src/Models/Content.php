<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Searchable;
use Illuminate\Support\Str;


/**
 * Content search model
 */
class Content extends Model
{
    use HasUuids;
    use Searchable;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'tenant_id' => '',
        'page_id' => null,
        'domain' => '',
        'path' => '',
        'lang' => '',
        'title' => '',
        'content' => '',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'page_id',
        'domain',
        'path',
        'lang',
        'title',
        'content',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_page_search';

    /**
     * Disable created_at and updated_at timestamps.
     */
    public $timestamps = false;


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
     * Generate a new unique key for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return strtoupper( (string) Str::uuid7() );
    }


    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return 'pages';
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    #[SearchUsingFullText(['content'])]
    public function toSearchableArray(): array
    {
        return [
            'content' => $this->content
        ];
    }


    /**
     * Interact with the "id" property.
     *
     * @return Attribute Eloquent attribute for the "id" property
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn( $value ) => strtoupper( $value ),
            set: fn( $value ) => strtoupper( $value ),
        );
    }
}