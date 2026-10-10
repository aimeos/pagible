<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;


/**
 * Language variant of a page
 *
 * Owns the language specific page data and the page versions. The read-only Page
 * model reads the variants through the page view, Resource writes them.
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
     * Page fields stored in the variant besides its language.
     */
    public const FIELDS = [...\Aimeos\Cms\Hashes::PAGE_FIELDS, 'path', 'domain', 'to', 'status'];


    /**
     * Default values of the variant attributes, also used by the page facade.
     */
    public const DEFAULTS = [
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
     * Casts of the variant attributes, also used by the page facade.
     */
    public const CASTS = [
        'cache' => 'integer',
        'status' => 'integer',
        'meta' => 'object',
        'config' => 'object',
        'content' => 'object', // for object access in templates
        'hashes' => 'array',
        'stale' => 'boolean',
    ];


    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = self::DEFAULTS;

    /**
     * The automatic casts for the attributes.
     *
     * @var array<string, string>
     */
    protected $casts = self::CASTS;

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
     * Prunes the trashed variants including their versions and search index entries in chunks.
     *
     * @param int $chunkSize Number of variants pruned at once
     * @return int Number of pruned variants
     */
    public function pruneAll( int $chunkSize = 1000 ) : int
    {
        $total = 0;

        $this->prunable()->withoutGlobalScope( SoftDeletingScope::class )->select( 'id', 'page_id', 'tenant_id' )->chunkById( $chunkSize, function( \Illuminate\Database\Eloquent\Collection $models ) use ( &$total ) {

            $ids = $models->modelKeys();

            Version::withoutTenancy()->whereIn( 'versionable_id', $ids )
                ->where( 'versionable_type', static::class )
                ->delete();

            $count = static::withoutTenancy()->withTrashed()->whereKey( $ids )->forceDelete();
            $total += $count;

            // source variants are listed for the languages of the pruned variants afterwards
            foreach( $models->groupBy( 'tenant_id' ) as $tenant => $list ) {
                \Aimeos\Cms\Tenancy::run( (string) $tenant, function() use ( $list ) {
                    \Aimeos\Cms\Scout::unindex( Page::class, array_map( strval( ... ), $list->modelKeys() ) );
                    \Aimeos\Cms\Scout::sources( $list->pluck( 'page_id' )->map( strval( ... ) )->all() );
                } );
            }

            event( new ModelsPruned( static::class, $count ) );
        } );

        return $total;
    }
}
