<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Query;

use Aimeos\Cms\Tenancy;
use Laravel\Scout\Builder;


/**
 * Scout builder that enforces the active tenant immediately before execution.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModel>
 */
class SearchBuilder extends Builder
{
    /**
     * Returns the search engine after restoring the mandatory tenant filter.
     */
    protected function engine()
    {
        $this->wheres = array_filter( $this->wheres,
            fn( $where, $key ) => SearchQuery::field( $where, $key ) !== 'tenant_id', ARRAY_FILTER_USE_BOTH );
        $this->where( 'tenant_id', Tenancy::value() );

        return parent::engine();
    }
}
