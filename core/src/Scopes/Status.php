<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Scopes;

use Aimeos\Cms\PageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Aimeos\Cms\Permission;


/**
 * Status scope for limiting query results.
 *
 * @implements Scope<Model>
 */
class Status implements Scope
{
    /**
     * Applys additional restrictions to the query builder.
     *
     * @param Builder<covariant Model> $builder Query builder
     * @param Model $model Eloquent model
     */
    public function apply( Builder $builder, Model $model ): void
    {
        if( !Permission::previews() ) {
            $builder->whereIn( $model->qualifyColumn( 'status' ), PageStatus::reachable() );
        }
    }
}
