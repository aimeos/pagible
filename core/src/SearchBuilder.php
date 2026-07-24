<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

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
        $wheres = [];

        foreach( $this->wheres as $key => $where )
        {
            $field = is_array( $where ) ? ( $where['field'] ?? $key ) : $key;

            if( $field !== 'tenant_id' ) {
                $wheres[$key] = $where;
            }
        }

        $this->wheres = $wheres;
        $this->where( 'tenant_id', Tenancy::value() );

        return parent::engine();
    }
}
