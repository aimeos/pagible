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

interface UpdatesResources
{

    /**
     * Update an existing resource.
     *
     * @param mixed|string $modelOrResourceId
     * @return ResourceBuilder
     */
    public function update($modelOrResourceId): ResourceBuilder;
}
