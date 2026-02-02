<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

interface Specification
{

    /**
     * Are client-generated ids allowed for the supplied resource type?
     *
     * @param string $resourceType
     * @return bool
     */
    public function clientIds(string $resourceType): bool;

    /**
     * Does the specified resource exist?
     *
     * @param string $resourceType
     * @param string $resourceId
     * @return bool
     */
    public function exists(string $resourceType, string $resourceId): bool;

    /**
     * Get the fields for the specified resource type.
     *
     * @param string $resourceType
     * @return iterable
     */
    public function fields(string $resourceType): iterable;

    /**
     * Get the supported resource types.
     *
     * @return string[]
     */
    public function types(): array;
}
