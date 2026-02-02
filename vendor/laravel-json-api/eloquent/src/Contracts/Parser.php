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

use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Pagination\Page;

interface Parser
{

    /**
     * Parse a single model.
     *
     * @param Model $model
     * @return object
     */
    public function parseOne(Model $model): object;

    /**
     * Parse a single model that may be null.
     *
     * @param Model|null $model
     * @return object|null
     */
    public function parseNullable(?Model $model): ?object;

    /**
     * Parse many models.
     *
     * @param $models
     * @return iterable
     */
    public function parseMany($models): iterable;

    /**
     * Parse a page of models.
     *
     * @param Page $page
     * @return Page
     */
    public function parsePage(Page $page): Page;
}
