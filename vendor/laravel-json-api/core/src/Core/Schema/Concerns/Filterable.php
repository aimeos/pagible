<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

use LaravelJsonApi\Contracts\Schema\Filter;

trait Filterable
{

    /**
     * @var array
     */
    private array $filters = [];

    /**
     * Set additional filters.
     *
     * @param Filter ...$filters
     * @return $this
     */
    public function withFilters(Filter ...$filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return iterable
     */
    public function filters(): iterable
    {
        return $this->filters;
    }
}
