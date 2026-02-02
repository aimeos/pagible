<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Resources;

use LaravelJsonApi\Core\Resources\JsonApiResource;

interface Factory
{

    /**
     * Can the factory create a resource for the supplied model?
     *
     * @param object $model
     * @return bool
     */
    public function canCreate(object $model): bool;

    /**
     * Create a resource object for the supplied model.
     *
     * @param object $model
     * @return JsonApiResource
     */
    public function createResource(object $model): JsonApiResource;
}
