<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Aimeos\Cms\Concerns\Broadcasts;
use Aimeos\Cms\Concerns\Connection;
use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\PrunesTrashed;
use Aimeos\Cms\Concerns\PrunesVersions;
use Aimeos\Cms\Concerns\SecondTimestamps;
use Aimeos\Cms\Concerns\Tenancy as HasTenancy;
use Aimeos\Cms\DB;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Publication;
use Laravel\Scout\Searchable;
use Aimeos\Cms\Exception;
use Aimeos\Cms\SearchBuilder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder;
use Aimeos\Cms\Tenancy;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;


/**
 * Base model for CMS entities (PageView, Element, File)
 *
 * Provides shared methods: DB connection, UUID generation, timestamps,
 * versioning relations, version cleanup, and pruning.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $editor
 * @property Version|null $latest
 * @property string|null $latest_id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static> withoutTenancy()
 * @method bool restore()
 */
abstract class Base extends Model
{
    use Broadcasts;
    use Connection;
    use HasUuids;
    use Prunable;
    use PrunesTrashed;
    use PrunesVersions;
    use Searchable;
    use SecondTimestamps;
    use SoftDeletes;
    use HasTenancy;

    public const MAX_BULK = 1000;

    /** @var int Number of items loaded and processed at once */
    public const CHUNK = 50;

    /** @var string Prefix of the permissions for the model, e.g. "page" for "page:publish" */
    public const PERM = '';

    /** @var int Number of stale versions removed at once */
    protected const PRUNE_SIZE = 500;

    /** @var list<string> Version relations referencing other items */
    protected const REFS = [];

    /** @var array<string, mixed>|null */
    protected ?array $changedInfo = null;

    /** @var class-string<Builder<Model>> */
    protected static string $scoutBuilder = SearchBuilder::class;

    /**
     * Returns the unique IDs and rejects them if they exceed the synchronous bulk limit.
     *
     * @param iterable<string> $ids Item IDs
     * @return list<string> Unique item IDs
     */
    public static function bulkIds( iterable $ids ) : array
    {
        $ids = array_values( array_unique( [...$ids] ) );
        static::checkBulk( count( $ids ) );

        return $ids;
    }


    /**
     * Rejects operations exceeding the synchronous bulk limit.
     */
    public static function checkBulk( int $count ) : void
    {
        if( $count > static::MAX_BULK ) {
            throw new Exception( sprintf(
                'No more than %d items may be changed at once.',
                static::MAX_BULK,
            ) );
        }
    }


    /**
     * Compare JSON casts independent of object key order.
     *
     * MySQL normalizes JSON object keys when storing values. Laravel's default
     * strict comparison therefore treats unchanged JSON as dirty when the same
     * value is encoded in a different key order.
     *
     * @param string $key Attribute name
     * @return bool TRUE if the current and original values are equivalent
     */
    public function originalIsEquivalent( $key )
    {
        if( $this->hasCast( $key, ['object', 'collection'] ) && array_key_exists( $key, $this->original ) ) {
            return self::canonicalJson( $this->fromJson( $this->attributes[$key] ?? null ) )
                === self::canonicalJson( $this->fromJson( $this->original[$key] ?? null ) );
        }

        return parent::originalIsEquivalent( $key );
    }


    /**
     * Recursively sort JSON object keys while retaining list order and value types.
     */
    private static function canonicalJson( mixed $value ) : mixed
    {
        if( !is_array( $value ) ) {
            return $value;
        }

        foreach( $value as $key => $item ) {
            $value[$key] = self::canonicalJson( $item );
        }

        if( !array_is_list( $value ) ) {
            ksort( $value );
        }

        return $value;
    }


    /**
     * Create a new Eloquent Collection without automatic relationship autoloading.
     *
     * @param array<array-key, Model> $models
     * @return Collection<array-key, static>
     */
    public function newCollection(array $models = [])
    {
        /** @var Collection<array-key, static> */
        return new Collection($models);
    }


    /**
     * Returns information about the changes that were made, if available.
     *
     * @return array<string, mixed>|null
     */
    public function getChangedAttribute() : ?array
    {
        return $this->changedInfo;
    }


    /**
     * Returns the value of the key the versions are referencing.
     *
     * @return string|null Key value
     */
    public function getVersionKey() : ?string
    {
        $value = $this->getAttribute( $this->getVersionKeyName() );
        return $value !== null ? (string) $value : null;
    }


    /**
     * Returns the type stored in the versions referencing the model.
     *
     * @return string Versionable type
     */
    protected static function versionType() : string
    {
        return static::class;
    }


    /**
     * Returns the attribute name of the key the versions are referencing.
     *
     * @return string Attribute name
     */
    protected function getVersionKeyName() : string
    {
        return $this->getKeyName();
    }


    /**
     * Get the model's latest version.
     *
     * @return BelongsTo<Version, $this> Eloquent relationship to the latest version
     */
    public function latest() : BelongsTo
    {
        return $this->belongsTo( Version::class, 'latest_id' );
    }


    /**
     * Scope that filters by version-level fields of the latest versions.
     *
     * By default, the latest version of each record is checked, which is fast if the other
     * conditions already select few records. Lookups by indexed version fields like domain
     * and path should select the matching versions first instead, which executes the version
     * queries immediately and must only be used if few versions match.
     *
     * @param EloquentBuilder<static> $query
     * @param array<string, mixed> $wheres Field => value pairs to filter on version data, lists match any of their values
     * @param bool $lookup TRUE to select the matching versions first using the version indexes
     * @return void
     */
    public function scopeWhereLatest( EloquentBuilder $query, array $wheres, bool $lookup = false ) : void
    {
        $table = $this->getTable();
        $arms = self::latestArms( $wheres, $lookup );

        if( !$lookup )
        {
            $query->where( "{$table}.latest_id", '=', fn( QueryBuilder $sub ) => $this->latestVersions( $sub, $arms[0], lookup: false ) );
            return;
        }

        // OR-ed IN subqueries can't be rewritten to semi-joins by most databases,
        // so the version IDs are fetched first by one index lookup for each arm
        $ids = [];

        foreach( $arms as $arm )
        {
            $sub = $this->getConnection()->query();
            $this->latestVersions( $sub, $arm, lookup: true );

            foreach( $sub->pluck( 'cms_versions.id' ) as $id ) {
                $ids[] = $id;
            }
        }

        $query->whereIn( "{$table}.latest_id", array_values( array_unique( $ids ) ) );
    }


    /**
     * Returns the combinations of the filter values, each one queried by a separate subquery.
     *
     * Empty values may be stored as NULL or not at all, so they also match NULL values.
     * Lookups use one subquery for NULL values so each one can use the version index.
     *
     * @param array<string, mixed> $wheres Field => value pairs to filter on version data, lists match any of their values
     * @param bool $lookup TRUE to split NULL values into separate combinations
     * @return non-empty-list<array<string, list<mixed>>> Lists of values by field for each combination
     */
    protected static function latestArms( array $wheres, bool $lookup ) : array
    {
        $arms = [[]];

        foreach( $wheres as $field => $value )
        {
            $list = is_array( $value ) ? array_values( $value ) : [$value];
            $empty = in_array( '', $list, true );
            $next = [];

            if( $empty && !$lookup ) {
                $list[] = null;
            }

            foreach( $arms as $arm )
            {
                if( $empty && $lookup ) {
                    $next[] = $arm + [$field => [null]];
                }

                $next[] = $arm + [$field => $list];
            }

            $arms = $next;
        }

        return $arms;
    }


    /**
     * Builds the subquery selecting the IDs of the versions matching the filter values.
     *
     * @param QueryBuilder $sub Query builder for the subquery
     * @param array<string, list<mixed>> $wheres Lists of values by field, matching any of their values
     * @param bool $lookup TRUE to select all matching versions, FALSE for the latest version of the model only
     */
    protected function latestVersions( QueryBuilder $sub, array $wheres, bool $lookup ) : void
    {
        $table = $this->getTable();
        $driver = $this->getConnection()->getDriverName();

        $sub->select( 'cms_versions.id' )
            ->from( 'cms_versions' )
            ->where( 'cms_versions.versionable_type', static::versionType() )
            ->where( 'cms_versions.tenant_id', Tenancy::value() );

        foreach( $wheres as $field => $list )
        {
            $column = DB::qualify( $field, $table, isDraft: true, driver: $driver ) ?? "{$table}.{$field}";

            $list === [null]
                ? $sub->whereNull( $column )
                : $sub->where( fn( $q ) => $q->whereIn( $column, array_filter( $list, fn( $v ) => $v !== null ) )
                    ->when( in_array( null, $list, true ), fn( $q ) => $q->orWhereNull( $column ) ) );
        }

        if( !$lookup ) {
            $sub->whereColumn( 'cms_versions.id', $table . '.latest_id' )->limit( 1 );
        }
    }


    /**
     * Creates a new latest version of the model with its references and saves the model.
     *
     * If no references are passed, the ones of the previous latest version are copied.
     * The change info is only set if conflicting changes have been merged.
     *
     * @param array<string, mixed> $values Version attributes like data, aux, lang and editor
     * @param array<string, array<string>>|null $refs Referenced IDs by version relation or NULL to keep the previous ones
     * @param array<string, mixed>|null $diffs Merge differences for the change info
     * @return Version New latest version
     */
    public function draft( array $values, ?array $refs = null, ?array $diffs = null ) : Version
    {
        $version = $this->newDraft( $values, $refs, $diffs );
        $this->save();

        return $version;
    }


    /**
     * Creates a new latest version of the model with its references without saving the model.
     *
     * The model must have its unique IDs. If no references are passed, the ones of the
     * previous latest version are copied. The change info is only set if conflicting
     * changes have been merged.
     *
     * @param array<string, mixed> $values Version attributes like data, aux, lang and editor
     * @param array<string, array<string>>|null $refs Referenced IDs by version relation or NULL to keep the previous ones
     * @param array<string, mixed>|null $diffs Merge differences for the change info
     * @return Version New latest version
     */
    public function newDraft( array $values, ?array $refs = null, ?array $diffs = null ) : Version
    {
        $editor = $this->latest->editor ?? '';
        $previous = $this->latest_id;

        $version = $this->versions()->forceCreate( $values );

        if( $refs !== null )
        {
            foreach( array_filter( $refs ) as $relation => $ids ) {
                $version->{$relation}()->attach( $ids );
            }
        }
        elseif( $previous )
        {
            $db = $this->getConnection();
            $id = $db->getDriverName() === 'pgsql' ? 'CAST(? AS uuid)' : '?';

            foreach( static::REFS as $relation )
            {
                $rel = $version->reference( $relation );
                $key = $rel->getRelatedPivotKeyName();
                $from = $rel->getForeignPivotKeyName();

                $db->table( $rel->getTable() )->insertUsing( [$from, $key], $db->table( $rel->getTable() )
                    ->selectRaw( $id, [$version->id] )->addSelect( $key )
                    ->where( $from, $previous ) );
            }
        }

        $this->setRelation( 'latest', $version );
        $this->forceFill( ['latest_id' => $version->id] );

        if( $diffs ) {
            $this->changedInfo = ['editor' => $editor, 'latest' => ['id' => $version->id]
                + array_intersect_key( $values, ['data' => 1, 'aux' => 1] ), ...$diffs];
        }

        return $version;
    }


    /**
     * Applies a lifecycle action to the locked items using set-based writes.
     *
     * @param Collection<int, Base> $items Items of the model
     * @param LifecycleAction $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    public static function lifecycle( Collection $items, LifecycleAction $action, string $editor ) : void
    {
        $ids = $items->modelKeys();

        if( $action === LifecycleAction::Purged )
        {
            static::withTrashed()->whereIn( 'id', $ids )->forceDelete();

            foreach( $items as $item ) {
                $item->exists = false;
                $item->wasRecentlyCreated = false;
            }

            return;
        }

        $time = static::query()->getModel()->freshTimestamp();
        $attributes = [
            'deleted_at' => $action === LifecycleAction::Dropped ? $time : null,
            'editor' => $editor,
            'updated_at' => $time,
        ];

        static::withTrashed()->whereIn( 'id', $ids )->update( $attributes );

        foreach( $items as $item ) {
            $item->forceFill( $attributes )->syncOriginalAttributes( array_keys( $attributes ) );
        }
    }


    /**
     * Invalidates the cached pages which reference the items.
     *
     * @param array<int, string> $ids Item IDs
     */
    public static function invalidateRefs( array $ids ) : void
    {
    }


    /**
     * Returns the columns a lifecycle action needs in addition to the identifying ones.
     *
     * @param LifecycleAction $action Lifecycle action
     * @return array<int, string> Column names
     */
    public static function lifecycleColumns( LifecycleAction $action ) : array
    {
        return [];
    }


    /**
     * Executes the callback while the resources of the model are locked.
     *
     * @template T
     * @param \Closure(): T $fcn Callback to execute
     * @return T Result of the callback
     */
    public static function locked( \Closure $fcn ) : mixed
    {
        return $fcn();
    }


    /**
     * Tests if purging removes stored data which can fail per item, so failed batches are purged item by item.
     */
    public static function purgesStorage() : bool
    {
        return false;
    }


    /**
     * Publishes a version and its dependencies.
     */
    public function publish( Version $version ) : void
    {
        ( new Publication() )->one( $this, $version );
    }


    /**
     * Returns the references of the latest versions of the given items in a few queries.
     *
     * Version references never change, so the result can be passed to draft() for
     * items whose latest version is still the same to avoid one query per relation.
     *
     * @param array<string> $ids Item IDs
     * @param string|null $lang Language of the page variants, unused for other items
     * @return array<string, array<string, array<string>>> Referenced IDs by relation, keyed by latest version ID
     */
    public static function refs( array $ids, ?string $lang = null ) : array
    {
        if( empty( static::REFS ) || empty( $ids ) ) {
            return [];
        }

        return static::versionRefs( static::withTrashed()->whereIn( 'id', $ids )->whereNotNull( 'latest_id' )->pluck( 'latest_id' )->all() );
    }


    /**
     * Returns the references of the given versions in a few queries.
     *
     * @param array<string> $versions Version IDs
     * @return array<string, array<string, array<string>>> Referenced IDs by relation, keyed by version ID
     */
    protected static function versionRefs( array $versions ) : array
    {
        $map = array_fill_keys( $versions, array_fill_keys( static::REFS, [] ) );
        $version = new Version();

        foreach( $versions ? static::REFS : [] as $relation )
        {
            $rel = $version->reference( $relation );
            $from = $rel->getForeignPivotKeyName();
            $key = $rel->getRelatedPivotKeyName();

            foreach( $version->getConnection()->table( $rel->getTable() )->whereIn( $from, $versions )->get( [$from, $key] ) as $row ) {
                $map[$row->{$from}][$relation][] = $row->{$key};
            }
        }

        return $map;
    }


    /**
     * Returns the searchable data of the item and its latest version.
     *
     * @param array<string> $attrs Attributes whose changes require reindexing
     * @param string $field Version data field which is indexed under the same name
     * @return array<string, mixed> Searchable data or empty if no indexed attribute has changed
     */
    protected function searchableVersion( array $attrs, string $field ) : array
    {
        if( !empty( $this->getChanges() ) && !$this->wasChanged( $attrs ) ) {
            return [];
        }

        $version = $this->latest;

        return [
            'content' => $this->trashed() ? '' : mb_strtolower( (string) $this ),
            'draft' => mb_strtolower( (string) $version ),
            'tenant_id' => $this->tenant_id ?? '',
            'lang' => $version?->lang,
            'editor' => $version->editor ?? '',
            $field => $version?->data->{$field} ?? '',
            'published' => (bool) ( $version->published ?? false ),
            'scheduled' => (int) ( $version?->data->scheduled ?? 0 ),
        ];
    }


    /**
     * Applies version values to this model without writing them.
     */
    public function stage( Version $version ) : void
    {
        $this->forceFill( [
            ...array_intersect_key( (array) $version->data, array_flip( $this->getFillable() ) ),
            ...$this->values( $version ),
            'editor' => $version->editor,
        ] );
        $this->setRelation( 'latest', $version );
    }


    /**
     * Get all of the model's versions.
     *
     * @return MorphMany<Version, $this> Eloquent relationship to the versions
     */
    public function versions() : MorphMany
    {
        return $this->morphMany( Version::class, 'versionable', null, null, $this->getVersionKeyName() )
            ->orderByDesc( 'created_at' )->orderByDesc( 'id' );
    }


    /**
     * Returns the common CMS model casts.
     *
     * @return array<string, string>
     */
    protected function casts() : array
    {
        return [
            'created_at' => 'datetime:Y-m-d H:i:s',
            'updated_at' => 'datetime:Y-m-d H:i:s',
            'deleted_at' => 'datetime:Y-m-d H:i:s',
        ];
    }


    /**
     * Loads the latest version needed by searchable CMS models.
     *
     * @param EloquentBuilder<static> $query
     * @return EloquentBuilder<static>
     */
    protected function makeAllSearchableUsing( $query )
    {
        return $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor', 'published' )] );
    }


    /**
     * Deletes versions before pruning a CMS model.
     */
    protected function pruning() : void
    {
        // pruning runs outside the tenant context of the item
        Version::withoutTenancy()->where( 'tenant_id', (string) $this->tenant_id )
            ->where( 'versionable_id', $this->getVersionKey() )
            ->where( 'versionable_type', $this->getMorphClass() )
            ->delete();
    }


    /**
     * Returns the language of the model reported in content events.
     *
     * @return string|null Language of page variants or NULL for models without language
     */
    public function eventLang() : ?string
    {
        return null;
    }


    /**
     * Returns the table, columns and row a published update of the model is written to.
     *
     * @param array<string, mixed> $row Row with the ID and the updated column values
     * @param array<string> $columns Updated columns
     * @return array{0: string, 1: array<string>, 2: array<string, mixed>} Table, columns and row
     */
    public function publishedRow( array $row, array $columns ) : array
    {
        return [$this->getTable(), $columns, $row];
    }


    /**
     * Returns model-specific values stored outside the version data payload.
     *
     * @return array<string, mixed>
     */
    protected function values( Version $version ) : array
    {
        return [];
    }
}
