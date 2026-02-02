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

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Eloquent\Polymorphism\MorphMany;

interface FillableToMany extends IsReadOnly
{

    /**
     * Fill the model with the value of the JSON:API to-many relation.
     *
     * @param Model $model
     * @param array $identifiers
     */
    public function fill(Model $model, array $identifiers): void;

    /**
     * Completely replace every member of the relationship with the specified members.
     *
     * @param Model $model
     * @param array $identifiers
     * @return EloquentCollection|MorphMany
     */
    public function sync(Model $model, array $identifiers): iterable;

    /**
     * Add the specified members to the relationship unless they are already present.
     *
     * @param Model $model
     * @param array $identifiers
     * @return EloquentCollection|MorphMany
     */
    public function attach(Model $model, array $identifiers): iterable;

    /**
     * Remove the specified members from the relationship.
     *
     * @param Model $model
     * @param array $identifiers
     * @return EloquentCollection|MorphMany
     */
    public function detach(Model $model, array $identifiers): iterable;
}
