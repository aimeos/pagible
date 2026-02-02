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

interface QueriesOne
{

    /**
     * Query a single resource.
     *
     * @param object|string $modelOrResourceId
     * @return QueryOneBuilder
     */
    public function queryOne($modelOrResourceId): QueryOneBuilder;
}
