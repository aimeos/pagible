<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Schema;

interface Container
{

    /**
     * Does a schema exist for the supplied resource type?
     *
     * @param string $resourceType
     * @return bool
     */
    public function exists(string $resourceType): bool;

    /**
     * Get a schema by JSON:API resource type.
     *
     * @param string $resourceType
     * @return Schema
     */
    public function schemaFor(string $resourceType): Schema;

    /**
     * Get a schema for the provided model class.
     *
     * @param string|object $model
     * @return Schema
     */
    public function schemaForModel($model): Schema;

    /**
     * Does a schema exist for the provided model class?
     *
     * @param string|object $model
     * @return bool
     */
    public function existsForModel($model): bool;

    /**
     * Get a list of all the supported resource types.
     *
     * @return array
     */
    public function types(): array;

}
