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
use Illuminate\Support\Facades\Date;
use Aimeos\Cms\Concerns\Broadcasts;
use Aimeos\Cms\Concerns\HasUuids;
use Aimeos\Cms\Concerns\Tenancy;
use Aimeos\Cms\DB;
use Aimeos\Cms\Publication;
use Laravel\Scout\Searchable;


/**
 * Base model for CMS entities (Page, Element, File)
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
 * @method bool restore()
 */
abstract class Base extends Model
{
    use Broadcasts;
    use HasUuids;
    use Prunable;
    use Searchable;
    use SoftDeletes;
    use Tenancy;

    public const MAX_BULK = 1000;

    /** @var string Prefix of the permissions for the model, e.g. "page" for "page:publish" */
    public const PERM = '';

    /** @var int Number of stale versions removed at once */
    protected const PRUNE_SIZE = 500;

    /** @var list<string> Version relations referencing other items */
    protected const REFS = [];

    /** @var array<string, mixed>|null */
    protected ?array $changedInfo = null;

    /** @var class-string<\Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>> */
    protected static string $scoutBuilder = \Aimeos\Cms\SearchBuilder::class;

    /**
     * Rejects operations exceeding the synchronous bulk limit.
     */
    public static function checkBulk( int $count ) : void
    {
        if( $count > static::MAX_BULK ) {
            throw new \Aimeos\Cms\Exception( sprintf(
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
     * @param array<array-key, \Illuminate\Database\Eloquent\Model> $models
     * @return \Illuminate\Database\Eloquent\Collection<array-key, static>
     */
    public function newCollection(array $models = [])
    {
        /** @var \Illuminate\Database\Eloquent\Collection<array-key, static> */
        return new \Illuminate\Database\Eloquent\Collection($models);
    }


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
     * Returns information about the changes that were made, if available.
     *
     * @return array<string, mixed>|null
     */
    public function getChangedAttribute() : ?array
    {
        return $this->changedInfo;
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
    public static function versionType() : string
    {
        return static::class;
    }


    /**
     * Returns the attribute name of the key the versions are referencing.
     *
     * @return string Attribute name
     */
    public function getVersionKeyName() : string
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
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @param array<string, mixed> $wheres Field => value pairs to filter on version data, lists match any of their values
     * @param bool $lookup TRUE to select the matching versions first using the version indexes
     * @return void
     */
    public function scopeWhereLatest( $query, array $wheres, bool $lookup = false ) : void
    {
        $table = $this->getTable();
        $driver = $this->getConnection()->getDriverName();

        $versions = fn( array $wheres ) => function( $sub ) use ( $table, $driver, $wheres, $lookup ) {
            $sub->select( 'cms_versions.id' )
                ->from( 'cms_versions' )
                ->where( 'cms_versions.versionable_type', static::versionType() )
                ->where( 'cms_versions.tenant_id', \Aimeos\Cms\Tenancy::value() );

            foreach( $wheres as $field => $list )
            {
                $column = DB::qualify( $field, $table, true, $driver ) ?? "{$table}.{$field}";

                $list === [null]
                    ? $sub->whereNull( $column )
                    : $sub->where( fn( $q ) => $q->whereIn( $column, array_filter( $list, fn( $v ) => $v !== null ) )
                        ->when( in_array( null, $list, true ), fn( $q ) => $q->orWhereNull( $column ) ) );
            }

            if( !$lookup ) {
                $sub->whereColumn( 'cms_versions.id', $table . '.latest_id' )->limit( 1 );
            }
        };

        $arms = [[]];

        foreach( $wheres as $field => $value )
        {
            $list = is_array( $value ) ? array_values( $value ) : [$value];
            $empty = in_array( '', $list, true );
            $next = [];

            // empty values may be stored as NULL or not at all
            if( $empty && !$lookup ) {
                $list[] = null;
            }

            foreach( $arms as $arm )
            {
                // lookups use one subquery for NULL values so each one can use the version index
                if( $empty && $lookup ) {
                    $next[] = $arm + [$field => [null]];
                }

                $next[] = $arm + [$field => $list];
            }

            $arms = $next;
        }

        if( !$lookup ) {
            $query->where( "{$table}.latest_id", '=', $versions( $arms[0] ) );
            return;
        }

        // OR-ed IN subqueries can't be rewritten to semi-joins by most databases,
        // so the version IDs are fetched first by one index lookup for each arm
        $ids = [];

        foreach( $arms as $arm )
        {
            $sub = $this->getConnection()->query();
            $versions( $arm )( $sub );

            foreach( $sub->pluck( 'cms_versions.id' ) as $id ) {
                $ids[] = $id;
            }
        }

        $query->whereIn( "{$table}.latest_id", array_values( array_unique( $ids ) ) );
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
                /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<Base, Version> $rel */
                $rel = $version->{$relation}();
                $key = $rel->getRelatedPivotKeyName();
                $from = $rel->getForeignPivotKeyName();

                $db->table( $rel->getTable() )->insertUsing( [$from, $key], $db->table( $rel->getTable() )
                    ->selectRaw( $id, [$version->id] )->addSelect( $key )
                    ->where( $from, $previous ) );
            }
        }

        $this->setRelation( 'latest', $version );
        $this->forceFill( ['latest_id' => $version->id] )->save();

        if( $diffs ) {
            $this->setChanged( ['editor' => $editor, 'latest' => ['id' => $version->id]
                + array_intersect_key( $values, ['data' => 1, 'aux' => 1] ), ...$diffs] );
        }

        return $version;
    }


    /**
     * Applies a lifecycle action to the locked items using set-based writes.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Base> $items Items of the model
     * @param 'dropped'|'purged'|'restored' $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    public static function lifecycle( \Illuminate\Database\Eloquent\Collection $items, string $action, string $editor ) : void
    {
        $ids = $items->modelKeys();

        if( $action === 'purged' )
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
            'deleted_at' => $action === 'dropped' ? $time : null,
            'editor' => $editor,
            'updated_at' => $time,
        ];

        static::withTrashed()->whereIn( 'id', $ids )->update( $attributes );

        foreach( $items as $item ) {
            $item->forceFill( $attributes )->syncOriginalAttributes( array_keys( $attributes ) );
        }
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
     * Removes old versions for several models using one ranked version stream.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Version owner IDs (variant IDs for pages)
     */
    public static function pruneVersions( string $tenant, array $ids ) : void
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids ) {
            return;
        }

        $num = max( 0, (int) config( 'cms.versions', 10 ) );
        $stale = new \SplTempFileObject( 1024 * 1024 );
        $stale->setFlags( \SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY );
        $stale->setCsvControl( ',', '"', '' );
        $keep = [];
        $found = false;

        foreach( static::versionRanks( $tenant, $ids ) as [$id, $rank] )
        {
            if( $rank > $num ) {
                $stale->fputcsv( [$id], ',', '"', '' );
                $found = true;
            } else {
                $keep[] = $id;
            }
        }

        if( !$found ) {
            return;
        }

        $prune = static::pruner( $tenant, $ids, $keep );
        $size = static::PRUNE_SIZE;
        $stale->rewind();
        $chunk = [];

        foreach( $stale as $row )
        {
            if( $row ) {
                $chunk[] = $row[0];
            }

            if( count( $chunk ) === $size ) {
                $prune( $chunk );
                $chunk = [];
            }
        }

        if( $chunk ) {
            $prune( $chunk );
        }
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
     * @return array<string, array<string, array<string>>> Referenced IDs by relation, keyed by latest version ID
     */
    public static function refs( array $ids ) : array
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
            /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<Base, Version> $rel */
            $rel = $version->{$relation}();
            $from = $rel->getForeignPivotKeyName();
            $key = $rel->getRelatedPivotKeyName();

            foreach( $version->getConnection()->table( $rel->getTable() )->whereIn( $from, $versions )->get( [$from, $key] ) as $row ) {
                $map[$row->{$from}][$relation][] = $row->{$key};
            }
        }

        return $map;
    }


    /**
     * Removes old versions of the model, keeping the configured number.
     */
    public function removeVersions() : void
    {
        if( ( $id = $this->getVersionKey() ) !== null ) {
            static::pruneVersions( $this->tenant_id, [$id] );
        }
    }


    /**
     * Sets information about the changes that were made.
     *
     * @param array<string, mixed> $info
     */
    public function setChanged( array $info ) : void
    {
        $this->changedInfo = $info;
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
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
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
     * Returns the callback removing a chunk of stale versions of the given models.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     * @param array<string> $keep IDs of the versions which are kept
     * @return \Closure(array<string>): void
     */
    protected static function pruner( string $tenant, array $ids, array $keep ) : \Closure
    {
        return function( array $chunk ) use ( $tenant ) {
            Version::withoutTenancy()->where( 'tenant_id', $tenant )->whereIn( 'id', $chunk )->forceDelete();
        };
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


    /**
     * Returns version IDs ranked newest first for each model.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     * @return \Generator<int, array{string, int}>
     */
    protected static function versionRanks( string $tenant, array $ids ) : \Generator
    {
        $owner = null;
        $rank = 0;

        foreach( Version::withoutTenancy()->select( 'id', 'versionable_id' )
            ->where( 'tenant_id', $tenant )
            ->where( 'versionable_type', static::versionType() )
            ->whereIn( 'versionable_id', $ids )
            ->orderBy( 'versionable_id' )->orderByDesc( 'created_at' )->orderByDesc( 'id' )
            ->cursor() as $version
        ) {
            $key = $version->versionable_id;

            if( $key !== $owner ) {
                $owner = $key;
                $rank = 0;
            }

            yield [(string) $version->id, ++$rank];
        }
    }
}
