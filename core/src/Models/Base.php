<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;


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
     * Prevent instantiation of abstract Base class by Laravel's HasCollection trait.
     *
     * @return class-string|null
     */
    public function resolveCollectionFromAttribute()
    {
        return null;
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
     * Get the model's published version.
     *
     * @return MorphOne<Version, $this> Eloquent relationship to the last published version
     */
    public function published() : MorphOne
    {
        return $this->morphOne( Version::class, 'versionable' )
            ->ofMany( ['created_at' => 'max', 'id' => 'max'], function( $query ) {
                $query->where( (new Version)->qualifyColumn( 'published' ), true );
            } );
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
