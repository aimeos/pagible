<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Date;


/**
 * Language variant of a page
 *
 * Owns the language specific page data and the page versions. Use the Page model
 * to read and write pages, it joins the variants transparently.
 *
 * @property string $id
 * @property string $page_id
 * @property string $tenant_id
 * @property string $lang
 * @property string $domain
 * @property string $path
 * @property string $to
 * @property string $name
 * @property string $title
 * @property string $type
 * @property string $theme
 * @property string $tag
 * @property int $cache
 * @property int $status
 * @property \stdClass $meta
 * @property \stdClass $config
 * @property \stdClass $content
 * @property array<string, string> $hashes
 * @property bool $stale
 * @property string|null $latest_id
 * @property string $editor
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> onlyTrashed()
 */
class PageVariant extends Model
{
    use HasUuids;
    use Prunable;
    use SoftDeletes;
    use Tenancy;


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'tenant_id' => '',
        'lang' => '',
        'domain' => '',
        'path' => '',
        'to' => '',
        'name' => '',
        'title' => '',
        'type' => '',
        'theme' => '',
        'tag' => '',
        'cache' => 5,
        'status' => 0,
        'meta' => '{}',
        'config' => '{}',
        'content' => '[]',
        'hashes' => '{}',
        'stale' => false,
        'editor' => '',
    ];

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cache' => 'integer',
        'status' => 'integer',
        'meta' => 'object',
        'config' => 'object',
        'content' => 'object',
        'hashes' => 'array',
        'stale' => 'boolean',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_page_variants';


    /**
     * Get the current timestamp in seconds precision.
     *
     * @return \Illuminate\Support\Carbon Current timestamp
     */
    public function freshTimestamp()
    {
        return Date::now()->startOfSecond(); // SQL Server workaround
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
     * Relation to the page the variant belongs to.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page() : BelongsTo
    {
        return $this->belongsTo( Page::class, 'page_id' );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning trashed variants
     */
    public function prunable() : Builder
    {
        return static::withoutTenancy()
            ->where( 'deleted_at', '<=', now()->subDays( config( 'cms.prune', 30 ) ) );
    }


    /**
     * Get all versions of the variant.
     *
     * @return MorphMany<Version, $this>
     */
    public function versions() : MorphMany
    {
        return $this->morphMany( Version::class, 'versionable' )->orderByDesc( 'created_at' )->orderByDesc( 'id' );
    }


    /**
     * Deletes the versions before pruning a variant.
     */
    protected function pruning() : void
    {
        \Aimeos\Cms\Tenancy::run( (string) $this->tenant_id, fn() => \Aimeos\Cms\Scout::unindex( Page::class, [(string) $this->id] ) );

        Version::withoutTenancy()->where( 'versionable_id', $this->id )
            ->where( 'versionable_type', static::class )
            ->delete();
    }
}
