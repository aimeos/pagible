<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;

use Illuminate\Database\Eloquent\Model;


/**
 * Provides multi-tenancy for models
 */
trait Tenancy
{
    /**
     * Returns the name of the tenant column.
     *
     * @return string Tenant column name
     */
    public function getTenantColumn() : string
    {
        return 'tenant_id';
    }


    /**
     * The "booted" method of the model.
     */
    protected static function booted() : void
    {
        static::addGlobalScope( new \Aimeos\Cms\Scopes\Tenancy() );

        static::creating( function( Model $model ) {
            $model->setAttribute( $model->getTenantColumn(), \Aimeos\Cms\Tenancy::value() );
        } );
    }
}
