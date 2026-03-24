<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;


/**
 * Tenancy class for tenancy value lookups.
 */
class Tenancy
{
    /**
     * Anonymous callback which provides the value of the current tenant.
     */
    public static ?\Closure $callback = null;

    /**
     * Current tenant value.
     */
    private string $id;


    /**
     * Creates a new tenancy instance with the given tenant ID.
     *
     * @param string $id Tenant ID
     */
    public function __construct( string $id )
    {
        $this->id = $id;
    }


    /**
     * Returns the tenant ID of this instance.
     *
     * @return string Tenant ID
     */
    public function id() : string
    {
        return $this->id;
    }


    /**
     * Returns the value for the tenant column in the models.
     *
     * @return string ID of the current tenant
     */
    public static function value() : string
    {
        return app( self::class )->id();
    }
}
