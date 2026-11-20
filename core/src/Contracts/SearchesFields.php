<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Contracts;

use Laravel\Scout\Builder;


/**
 * Scout engines which limit the full-text search to the fields passed to searchFields().
 */
interface SearchesFields
{
    /**
     * Limits the full-text search of the builder to the given fields.
     *
     * @param Builder<\Illuminate\Database\Eloquent\Model> $builder Scout search builder
     * @param array<string> $fields Searchable fields like "content" or "draft"
     */
    public function searchFields( Builder $builder, array $fields ) : void;
}
