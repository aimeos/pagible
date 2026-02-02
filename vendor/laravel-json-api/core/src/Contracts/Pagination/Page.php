<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Pagination;

use Countable;
use Illuminate\Contracts\Support\Responsable;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Query\QueryParameters;
use LaravelJsonApi\Core\Document\Links;

interface Page extends IteratorAggregate, Countable, Responsable
{

    /**
     * Get the page meta.
     *
     * @return array
     */
    public function meta(): array;

    /**
     * Get the page links.
     *
     * @return Links
     */
    public function links(): Links;

    /**
     * Specify the query string parameters that should be present on pagination links.
     *
     * @param QueryParameters|array|mixed $query
     * @return $this
     */
    public function withQuery($query): self;
}
