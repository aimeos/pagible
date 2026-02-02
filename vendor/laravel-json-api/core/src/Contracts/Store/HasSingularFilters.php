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

interface HasSingularFilters
{

    /**
     * Execute the query and return the result.
     *
     * If a singular filter has been applied, this method MUST return
     * the first matching model, or null.
     *
     * Otherwise, this method MUST return an iterable of all matching models.
     *
     * @return iterable|object|null
     */
    public function firstOrMany();

    /**
     * Execute the query, with support for singular filters.
     *
     * If the supplied page variable is empty, this method MUST return:
     *
     * - the first matching model or null if a singular filter has been applied; OR
     * - a page of matching models, if default pagination is always used; OR
     * - all the matching models.
     *
     * If the supplied page variable is not empty AND pagination is supported,
     * this method MUST return a page of matching models.
     *
     * @param array|null $page
     * @return object|Page|iterable|null
     */
    public function firstOrPaginate(?array $page);
}
