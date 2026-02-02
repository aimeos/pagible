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

interface FillableToOne extends IsReadOnly
{

    /**
     * Does the model need to exist in the database before the relation is filled?
     *
     * @return bool
     */
    public function mustExist(): bool;

    /**
     * Fill the model with the value of the JSON:API to-one relation.
     *
     * @param Model $model
     * @param mixed $identifier
     */
    public function fill(Model $model, ?array $identifier): void;

    /**
     * Replace the relationship.
     *
     * @param Model $model
     * @param array|null $identifier
     * @return Model|null
     */
    public function associate(Model $model, ?array $identifier): ?Model;
}
