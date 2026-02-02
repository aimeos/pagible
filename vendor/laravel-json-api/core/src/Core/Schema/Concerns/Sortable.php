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

trait Sortable
{

    /**
     * @var bool
     */
    private bool $sortable = false;

    /**
     * Mark the field as sortable.
     *
     * @return $this
     */
    public function sortable(): self
    {
        $this->sortable = true;

        return $this;
    }

    /**
     * Mark the field as not sortable.
     *
     * @return $this
     */
    public function notSortable(): self
    {
        $this->sortable = false;

        return $this;
    }

    /**
     * Is the field sortable?
     *
     * @return bool
     */
    public function isSortable(): bool
    {
        return $this->sortable;
    }
}
