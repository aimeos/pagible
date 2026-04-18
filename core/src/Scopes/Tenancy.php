<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;


/**
 * Tenancy scope for limiting query results.
 */
class Tenancy implements Scope
{
    /**
     * Applys additional restrictions to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $builder Query builder
     * @param \Illuminate\Database\Eloquent\Model $model Eloquent model
     */
    public function apply( Builder $builder, Model $model ): void
    {
        /** @phpstan-ignore method.notFound */
        $builder->where( $model->qualifyColumn( $model->getTenantColumn() ), \Aimeos\Cms\Tenancy::value() );
    }


    /**
     * Adds additional macros to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $builder Query builder
     */
    public function extend( Builder $builder ): void
    {
        $scope = $this;
        $builder->macro( 'withoutTenancy', fn( Builder $builder ) => $builder->withoutGlobalScope( $scope ));
    }
}