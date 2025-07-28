<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


namespace Aimeos\Cms\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;


/**
 * Status scope for limiting query results.
 */
class Status implements Scope
{
    /**
     * Applys additional restrictions to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder Query builder
     * @param \Illuminate\Database\Eloquent\Model $model Eloquent model
     */
    public function apply( Builder $builder, Model $model )
    {
        if( !\Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->where( $model->qualifyColumn( 'status' ), '>', 0 );
        }
    }


    /**
     * Adds additional macros to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder Query builder
     */
    public function extend( Builder $builder )
    {
        $builder->macro( 'withoutStatus', function( Builder $builder ) {
            return $builder->withoutGlobalScope( $this );
        });
    }
}