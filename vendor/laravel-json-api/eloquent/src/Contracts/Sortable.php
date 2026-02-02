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

interface Sortable
{

    /**
     * Apply the sort order to the query.
     *
     * @param Builder $query
     * @param string $direction
     * @return Builder
     */
    public function sort($query, string $direction = 'asc');
}
