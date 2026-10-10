<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Filters;

use LaravelJsonApi\Eloquent\Filters\Where;


/**
 * Filters pages by a column of their language variants, e.g. the path.
 *
 * Every variant has its own path, so all variants are searched instead of the
 * source variants only. A "lang" filter applied afterwards limits the result to
 * the variants of that language.
 */
class WhereVariant extends Where
{
    /**
     * Applies the filter to the page query.
     *
     * @param \Aimeos\Cms\Query\PageQuery<\Aimeos\Cms\Models\Page> $query
     * @param mixed $value
     * @return \Aimeos\Cms\Query\PageQuery<\Aimeos\Cms\Models\Page>
     */
    public function apply( $query, $value )
    {
        parent::apply( $query->allVariants(), $value );
        return $query;
    }
}
