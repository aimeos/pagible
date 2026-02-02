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

interface ModifiesToOne
{

    /**
     * Modify a to-one relationship.
     *
     * @param object|string $modelOrResourceId
     * @param string $fieldName
     * @return ToOneBuilder
     */
    public function modifyToOne($modelOrResourceId, string $fieldName): ToOneBuilder;
}
