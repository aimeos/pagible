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
use Illuminate\Database\Eloquent\Model;

interface Driver
{

    /**
     * Return a new query instance for querying specific resources.
     *
     * @return Builder
     */
    public function query(): Builder;

    /**
     * Return a new query instance for querying all resources.
     *
     * @return Builder
     */
    public function queryAll(): Builder;

    /**
     * Create a new model instance.
     *
     * @return Model
     */
    public function newInstance(): Model;

    /**
     * Persist the model to the database.
     *
     * @param Model $model
     * @return bool
     *      whether the storage operation was successful.
     */
    public function persist(Model $model): bool;

    /**
     * Remove the model from the database.
     *
     * @param Model $model
     * @return bool
     *      whether the removal operation was successful.
     */
    public function destroy(Model $model): bool;
}
