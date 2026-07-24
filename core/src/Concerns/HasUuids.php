<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids as LaravelUuids;


/**
 * Provides portable UUID generation and SQL Server representation.
 */
trait HasUuids
{
    use LaravelUuids {
        newUniqueId as private uuid;
    }


    /**
     * Returns IDs in the canonical SQL Server UUID representation.
     */
    public function getIdAttribute( ?string $value ) : ?string
    {
        return $value !== null && $this->getConnection()->getDriverName() === 'sqlsrv'
            ? strtoupper( $value )
            : $value;
    }


    /**
     * Generates a UUID using the canonical SQL Server representation.
     */
    public function newUniqueId() : string
    {
        $id = $this->uuid();

        return $this->getConnection()->getDriverName() === 'sqlsrv' ? strtoupper( $id ) : $id;
    }


    /**
     * Assigns missing IDs without reading them through the accessor.
     */
    public function setUniqueIds() : void
    {
        foreach( $this->uniqueIds() as $column ) {
            $this->attributes[$column] ??= $this->newUniqueId();
        }
    }
}
