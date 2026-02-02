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

interface HasPagination
{

    /**
     * Return a page of models using JSON:API page parameters.
     *
     * @param array $page
     * @return Page
     */
    public function paginate(array $page): Page;

    /**
     * Execute the query.
     *
     * If the supplied page variable is empty, this method MUST return:
     *
     * - a page of matching models, if default pagination is always used; OR
     * - all the matching models.
     *
     * If the supplied page variable is not empty, this method MUST return
     * a page of matching models.
     *
     * @param array|null $page
     * @return object|Page|iterable|null
     */
    public function getOrPaginate(?array $page): iterable;
}
