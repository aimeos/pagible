<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;


/**
 * Tenancy scope for limiting query results.
 *
 * @implements Scope<Model>
 */
class Tenancy implements Scope
{
    /**
     * Applys additional restrictions to the query builder.
     *
     * @param Builder<covariant Model> $builder Query builder
     * @param Model $model Eloquent model
     */
    public function apply( Builder $builder, Model $model ): void
    {
        // models without the Tenancy trait use the default tenant column
        $column = method_exists( $model, 'getTenantColumn' ) ? $model->getTenantColumn() : 'tenant_id';
        $builder->where( $model->qualifyColumn( $column ), \Aimeos\Cms\Tenancy::value() );
    }


    /**
     * Adds additional macros to the query builder.
     *
     * @param Builder<Model> $builder Query builder
     */
    public function extend( Builder $builder ): void
    {
        $scope = $this;
        $builder->macro( 'withoutTenancy', fn( Builder $builder ) => $builder->withoutGlobalScope( $scope ));
    }
}
