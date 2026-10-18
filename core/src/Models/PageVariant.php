<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Aimeos\Cms\Casts\AsString;
use Aimeos\Cms\Concerns\Connection;
use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\PageAttributes;
use Aimeos\Cms\Concerns\PrunesTrashed;
use Aimeos\Cms\Concerns\SecondTimestamps;
use Aimeos\Cms\Concerns\Tenancy as HasTenancy;
use Aimeos\Cms\DB;
use Aimeos\Cms\Scout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Eloquent\Collection;
use Aimeos\Cms\Tenancy;


/**
 * Language variant of a page
 *
 * Owns the language specific page data and the page versions. The read-only
 * PageView model reads the pages joined with their variants.
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
 * @property-read Page $page
 * @property-read Version|null $latest
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Element> $elements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, File> $files
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Version> $versions
 * @property-read PageView $view
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> onlyTrashed()
 */
class PageVariant extends Model
{
    use Connection;
    use HasUuids;
    use PageAttributes;
    use Prunable;
    use PrunesTrashed;
    use SecondTimestamps;
    use SoftDeletes;
    use HasTenancy;

    /**
     * Columns stored in the cms_page_variants table besides the IDs and the timestamps.
     */
    public const FIELDS = [
        'lang', 'domain', 'path', 'to', 'name', 'title', 'type', 'theme', 'tag', 'cache', 'status',
        'meta', 'config', 'content', 'hashes', 'stale', 'latest_id', 'editor',
    ];

    /**
     * Page fields tracked for translations, the language specific fields are excluded.
     */
    public const SYNC_FIELDS = ['title', 'name', 'type', 'theme', 'tag', 'cache'];

    /**
     * Page fields stored in the version data besides the language.
     */
    public const DATA_FIELDS = [...self::SYNC_FIELDS, 'path', 'domain', 'to', 'status'];


    /**
     * Default values of the variant attributes, also used by the page view.
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
     * Casts of the variant attributes, also used by the page view.
     */
    public const CASTS = [
        'tag' => AsString::class,
        'lang' => 'string',
        'path' => AsString::class,
        'domain' => 'string',
        'to' => AsString::class,
        'name' => AsString::class,
        'title' => 'string',
        'type' => AsString::class,
        'theme' => AsString::class,
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
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = self::FIELDS;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cms_page_variants';


    /**
     * Updates the source language of the page and the search index.
     */
    protected static function booted() : void
    {
        static::updated( function( PageVariant $variant ) {
            // the source language follows the language of the source variant
            if( $variant->wasChanged( 'lang' ) ) {
                Page::withTrashed()->whereKey( $variant->page_id )->where( 'source', $variant->getOriginal( 'lang' ) )
                    ->toBase()->update( ['source' => $variant->lang] );

                // the other variants are listed for the languages of the old and new fallback chains
                if( Scout::syncs( PageView::class ) ) {
                    Scout::variants( [(string) $variant->page_id], [(string) $variant->getOriginal( 'lang' ), (string) $variant->lang] );
                }
            }
        } );

        static::saved( fn( PageVariant $variant ) => $variant->index() );
    }


    /**
     * Relation to the shared elements referenced by the published variant.
     *
     * @return BelongsToMany<Element, $this>
     */
    public function elements() : BelongsToMany
    {
        return $this->belongsToMany( Element::class, 'cms_page_element', 'variant_id', 'element_id' );
    }


    /**
     * Relation to the files referenced by the published variant.
     *
     * @return BelongsToMany<File, $this>
     */
    public function files() : BelongsToMany
    {
        return $this->belongsToMany( File::class, 'cms_page_file', 'variant_id', 'file_id' );
    }


    /**
     * Relation to the latest version of the variant.
     *
     * @return BelongsTo<Version, $this>
     */
    public function latest() : BelongsTo
    {
        return $this->belongsTo( Version::class, 'latest_id' );
    }


    /**
     * Relation to the page the variant belongs to, including trashed pages.
     *
     * @return BelongsTo<Page, $this>
     */
    public function page() : BelongsTo
    {
        return $this->belongsTo( Page::class, 'page_id' )->withTrashed();
    }


    /**
     * Relation to the versions of the variant.
     *
     * @return MorphMany<Version, $this>
     */
    public function versions() : MorphMany
    {
        return $this->morphMany( Version::class, 'versionable' )->orderByDesc( 'created_at' )->orderByDesc( 'id' );
    }


    /**
     * Relation to the page joined with this variant.
     *
     * @return HasOne<PageView, $this>
     */
    public function view() : HasOne
    {
        return $this->hasOne( PageView::class, 'variant_id' )->withTrashed()->allVariants( trashed: true );
    }


    /**
     * Get the prunable model query.
     *
     * @return Builder<static> Eloquent query builder for pruning trashed variants
     */
    public function prunable() : Builder
    {
        return static::expired();
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

        $this->prunable()->withoutGlobalScope( SoftDeletingScope::class )->select( 'id', 'page_id', 'tenant_id', 'lang' )->chunkById( $chunkSize, function( Collection $models ) use ( &$total ) {

            $count = static::purge( array_map( strval( ... ), $models->modelKeys() ) );
            $total += $count;

            // other variants are listed for the languages of the pruned variants afterwards
            foreach( $models->groupBy( 'tenant_id' ) as $tenant => $list ) {
                Tenancy::run( (string) $tenant, function() use ( $list ) {
                    Scout::unindex( PageView::class, array_map( strval( ... ), $list->modelKeys() ) );
                    Scout::variants( $list->pluck( 'page_id' )->map( strval( ... ) )->all(), $list->pluck( 'lang' )->map( strval( ... ) )->unique()->all() );
                } );
            }

            event( new ModelsPruned( static::class, $count ) );
        } );

        return $total;
    }


    /**
     * Permanently deletes the variants including their versions.
     *
     * The variants are deleted without the global scopes, so the caller must pass
     * only the IDs of the variants it's allowed to delete. Uses ID lists instead of
     * subqueries because older MySQL/MariaDB versions evaluate IN subqueries of
     * single table deletes for each row.
     *
     * @param array<string> $ids Variant IDs
     * @return int Number of deleted variants
     */
    public static function purge( array $ids ) : int
    {
        $count = 0;

        foreach( array_chunk( array_values( $ids ), DB::IN_SIZE ) as $chunk )
        {
            static::purgeVersions( $chunk );
            $count += static::withoutGlobalScopes()->whereKey( $chunk )->toBase()->delete();
        }

        return $count;
    }


    /**
     * Permanently deletes the versions of the variants.
     *
     * The versions are deleted without the global scopes, so the caller must pass
     * only the IDs of the variants it's allowed to delete.
     *
     * @param array<string> $ids Variant IDs
     */
    public static function purgeVersions( array $ids ) : void
    {
        foreach( array_chunk( array_values( $ids ), DB::IN_SIZE ) as $chunk ) {
            Version::withoutGlobalScopes()->where( 'versionable_type', static::class )->whereIn( 'versionable_id', $chunk )->toBase()->delete();
        }
    }


    /**
     * Updates the search index entry of the variant after the transaction commits.
     */
    protected function index() : void
    {
        if( Scout::syncs( PageView::class ) ) {
            Scout::reindex( PageView::class, [(string) $this->id], keys: true );
        }
    }

}
