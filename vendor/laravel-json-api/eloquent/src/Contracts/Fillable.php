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

interface Fillable extends IsReadOnly
{

    /**
     * Does the model need to exist in the database before the attribute is filled?
     *
     * @return bool
     */
    public function mustExist(): bool;

    /**
     * Fill the model with the value of the JSON:API attribute.
     *
     * @param Model $model
     * @param mixed $value
     * @param array $validatedData
     * @return void
     */
    public function fill(Model $model, $value, array $validatedData): void;
}
