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

interface QueriesToMany
{

    /**
     * Query a to-many relation.
     *
     * @param object|string $modelOrResourceId
     * @param string $fieldName
     * @return QueryManyBuilder|HasPagination
     */
    public function queryToMany($modelOrResourceId, string $fieldName): QueryManyBuilder;
}
