<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Store;

use LaravelJsonApi\Contracts\Store\HasSingularFilters;

class QueryAllHandler extends QueryManyHandler implements HasSingularFilters
{

    /**
     * @inheritDoc
     */
    public function firstOrMany()
    {
        if ($this->builder instanceof HasSingularFilters) {
            return $this->builder->firstOrMany();
        }

        return $this->builder->get();
    }

    /**
     * @inheritDoc
     */
    public function firstOrPaginate(?array $page)
    {
        if ($this->builder instanceof HasSingularFilters) {
            return $this->builder->firstOrPaginate($page);
        }

        return $this->getOrPaginate($page);
    }

}
