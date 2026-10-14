<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;


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
     * Boots tenant scoping for the model.
     */
    protected static function bootTenancy() : void
    {
        static::addGlobalScope( new \Aimeos\Cms\Scopes\Tenancy() );

        static::creating( function( self $model ) {
            $model->setAttribute( $model->getTenantColumn(), \Aimeos\Cms\Tenancy::value() );
        } );
    }
}
