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
     * Boots tenant scoping for the model.
     */
    protected static function bootTenancy() : void
    {
        static::addGlobalScope( new \Aimeos\Cms\Scopes\Tenancy() );

        static::creating( function( self $model ) {
            $model->setAttribute( 'tenant_id', \Aimeos\Cms\Tenancy::value() );
        } );
    }
}
