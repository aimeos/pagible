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

interface QueryOneBuilder extends Builder
{

    /**
     * Filter the model using JSON:API filter parameters.
     *
     * @param array|null $filters
     * @return $this
     */
    public function filter(?array $filters): self;

    /**
     * Execute the query and get the first result.
     *
     * @return object|null
     */
    public function first(): ?object;
}
