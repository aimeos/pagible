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
    private static ?string $value = null;


    /**
     * Returns the value for the tenant column in the models.
     *
     * @return string ID of the current tenant
     */
    public static function value() : string
    {
        if( self::$value === null && self::$callback !== null )
        {
            $closure = self::$callback;
            self::$value = $closure();
        }

        return (string) self::$value;
    }
}
