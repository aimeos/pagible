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

interface Repository
{

    /**
     * Get the model for the supplied resource id.
     *
     * @param string $resourceId
     * @return object|null
     */
    public function find(string $resourceId): ?object;

    /**
     * Get the models for the supplied resource ids.
     *
     * @param string[] $resourceIds
     * @return iterable
     */
    public function findMany(array $resourceIds): iterable;

    /**
     * Find the supplied model or throw a runtime exception if it does not exist.
     *
     * @param string $resourceId
     * @return object
     */
    public function findOrFail(string $resourceId): object;

    /**
     * Does a model with the supplied resource id exist?
     *
     * @param string $resourceId
     * @return bool
     */
    public function exists(string $resourceId): bool;
}
