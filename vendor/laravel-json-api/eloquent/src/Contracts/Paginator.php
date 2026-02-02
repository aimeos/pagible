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
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Contracts\Pagination\Paginator as BasePaginator;

interface Paginator extends BasePaginator
{

    /**
     * Set the key column.
     *
     * The key column must be used to ensure the paginator
     * has a deterministic order. Typically this will be set to
     * either the model's key name or the model's route key name.
     *
     * @param string $column
     * @return $this
     * @see https://github.com/cloudcreativity/laravel-json-api/issues/313
     */
    public function withKeyName(string $column): self;

    /**
     * Set the columns to select when querying the database.
     *
     * @param string|array $columns
     * @return $this
     */
    public function withColumns($columns): self;

    /**
     * Execute the query and return a JSON API page.
     *
     * @param Builder|Relation $query
     * @param array $page
     * @return Page
     */
    public function paginate($query, array $page): Page;
}
