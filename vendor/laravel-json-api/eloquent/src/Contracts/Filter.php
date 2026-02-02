<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Contracts;

use Illuminate\Database\Eloquent\Builder;
use LaravelJsonApi\Contracts\Schema\Filter as BaseFilter;

interface Filter extends BaseFilter
{

    /**
     * Does the filter return a singular resource?
     *
     * Return `true` if the filter will return a singular resource, rather than a list
     * of resources.
     *
     * @return bool
     */
    public function isSingular(): bool;

    /**
     * Apply the filter to the query.
     *
     * @param Builder $query
     * @param mixed $value
     * @return Builder
     */
    public function apply($query, $value);
}
