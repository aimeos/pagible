<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Store;

use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Core\Query\SortField;
use LaravelJsonApi\Core\Query\SortFields;

interface QueryManyBuilder extends Builder
{

    /**
     * Filter models using JSON:API filter parameters.
     *
     * @param array|null $filters
     * @return $this
     */
    public function filter(?array $filters): self;

    /**
     * Sort models using JSON:API sort fields.
     *
     * @param SortFields|SortField|array|string|null $fields
     * @return $this
     */
    public function sort($fields): self;

    /**
     * Get all the results of the query.
     *
     * The method MAY return a page, if it is not possible to retrieve all
     * resources in a single query.
     *
     * @return iterable|Page
     */
    public function get(): iterable;

}
