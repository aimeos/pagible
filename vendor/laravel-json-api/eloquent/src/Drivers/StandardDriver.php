<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Eloquent\Contracts\Driver;

class StandardDriver implements Driver
{

    /**
     * @var Model
     */
    protected Model $model;

    /**
     * StandardDriver constructor.
     *
     * @param Model $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @inheritDoc
     */
    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @inheritDoc
     */
    public function queryAll(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @inheritDoc
     */
    public function newInstance(): Model
    {
        return $this->model->newInstance();
    }

    /**
     * @inheritDoc
     */
    public function persist(Model $model): bool
    {
        return (bool) $model->save();
    }

    /**
     * @inheritDoc
     */
    public function destroy(Model $model): bool
    {
        return (bool) $model->delete();
    }

}
