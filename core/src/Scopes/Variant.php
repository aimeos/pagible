<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Scopes;

use Aimeos\Cms\Query\PageBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;


/**
 * Selects the variants of the pages read from the page view.
 *
 * @implements Scope<Model>
 */
class Variant implements Scope
{
    /**
     * Applys additional restrictions to the query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder<covariant \Illuminate\Database\Eloquent\Model> $builder Query builder
     * @param \Illuminate\Database\Eloquent\Model $model Eloquent model
     */
    public function apply( Builder $builder, Model $model ): void
    {
        $query = $builder->getQuery();

        if( $query instanceof PageBuilder ) {
            $query->whereVariant();
        }
    }
}
