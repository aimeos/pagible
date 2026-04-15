<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Aimeos\Cms\DB;


/**
 * Base model for CMS entities (Page, Element, File)
 *
 * Provides shared methods: DB connection, UUID generation, timestamps,
 * versioning relations, version cleanup, and pruning.
 *
 * @property string $id
 * @property string $editor
 * @property Version|null $latest
 * @method static \Illuminate\Database\Eloquent\Builder<static> withTrashed()
 * @method self publish(Version $version)
 * @method bool restore()
 */
abstract class Base extends Model
{
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

        $query->select( "{$table}.*" )
            ->join( 'cms_versions', "{$table}.latest_id", '=', 'cms_versions.id' )
            ->where( 'cms_versions.versionable_type', static::class )
            ->where( 'cms_versions.tenant_id', \Aimeos\Cms\Tenancy::value() );

        foreach( $wheres as $field => $value ) {
            $query->where( DB::qualify( $field, $table, true, $driver ) ?? "{$table}.{$field}", $value );
        }
    }


    /**
     * Normalize UUID case on SQL Server to prevent mixed-case mismatches.
     *
     * @param string|null $value Raw ID value
     * @return string|null Uppercased on SQL Server, unchanged otherwise
     */
    public function getIdAttribute( $value )
    {
        return $this->getConnection()->getDriverName() === 'sqlsrv' && $value ? strtoupper( $value ) : $value;
    }


    /**
     * Generate a new unique key for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        // workaround for SQL Server and Lighthouse when UUIDs are mixed case
        return (string) ( $this->getConnection()->getDriverName() === 'sqlsrv' ? strtoupper( Str::uuid7() ) : Str::uuid7() );
    }


    /**
     * Removes old versions of the model, keeping the configured number.
     *
     * @return static The current instance for method chaining
     */
    public function removeVersions() : static
    {
        $num = config( 'cms.versions', 10 );

        // MySQL doesn't support offsets for DELETE
        $ids = Version::where( 'versionable_id', $this->id )
            ->where( 'versionable_type', static::class )
            ->orderByDesc( 'created_at' )
            ->offset( $num )
            ->limit( 10 )
            ->pluck( 'id' );

        if( !$ids->isEmpty() ) {
            Version::whereIn( 'id', $ids )->forceDelete();
        }

        return $this;
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
}
