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
     * Get the model's latest version.
     *
     * @return BelongsTo<Version, $this> Eloquent relationship to the latest version
     */
    public function latest() : BelongsTo
    {
        return $this->belongsTo( Version::class, 'latest_id' );
    }


    /**
     * Scope that joins cms_versions and filters by version-level fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder<static> $query
     * @param array<string, mixed> $wheres Field => value pairs to filter on version data
     * @return void
     */
    public function scopeWhereLatest( $query, array $wheres ) : void
    {
        $table = $this->getTable();
        $driver = $this->getConnection()->getDriverName();

        $query->where( "{$table}.latest_id", '=', function( $sub ) use ( $table, $driver, $wheres ) {
            $sub->select( 'cms_versions.id' )
                ->from( 'cms_versions' )
                ->whereColumn( 'cms_versions.id', $table . '.latest_id' )
                ->where( 'cms_versions.versionable_type', static::class )
                ->where( 'cms_versions.tenant_id', \Aimeos\Cms\Tenancy::value() );

            foreach( $wheres as $field => $value ) {
                $sub->where( DB::qualify( $field, $table, true, $driver ) ?? "{$table}.{$field}", $value );
            }

            $sub->limit( 1 );
        } );
    }


    /**
     * Removes old versions for several models using one ranked version stream.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
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

        foreach( static::versionRanks( $tenant, $ids ) as [$id, $rank] ) {
            if( $rank > $num ) {
                $stale->fputcsv( [$id], ',', '"', '' );
            }
        }

        $stale->rewind();
        $chunk = [];

        foreach( $stale as $row )
        {
            if( $row ) {
                $chunk[] = $row[0];
            }

            if( count( $chunk ) === 500 ) {
                Version::withoutTenancy()->where( 'tenant_id', $tenant )
                    ->whereIn( 'id', $chunk )->forceDelete();
                $chunk = [];
            }
        }

        if( $chunk ) {
            Version::withoutTenancy()->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $chunk )->forceDelete();
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
     * Removes old versions of the model, keeping the configured number.
     */
    public function removeVersions() : void
    {
        if( ( $id = $this->id ) !== null ) {
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
        return $this->morphMany( Version::class, 'versionable' )->orderByDesc( 'created_at' )->orderByDesc( 'id' );
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
        return $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor', 'published' )] );
    }


    /**
     * Deletes versions before pruning a CMS model.
     */
    protected function pruning() : void
    {
        Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', static::class )
            ->delete();
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
            ->where( 'versionable_type', static::class )
            ->whereIn( 'versionable_id', $ids )
            ->orderBy( 'versionable_id' )->orderByDesc( 'created_at' )->orderByDesc( 'id' )
            ->cursor() as $version
        ) {
            $key = $version->versionable_id;

            if( $key !== $owner ) {
                $owner = $key;
                $rank = 0;
            }

            if( ( $id = $version->id ) === null ) {
                throw new \LogicException( 'Stored CMS version has no ID.' );
            }

            yield [$id, ++$rank];
        }
    }
}
