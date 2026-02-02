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

trait SparseField
{

    /**
     * @var bool
     */
    private bool $sparseField = true;

    /**
     * Mark the field as not allowed in sparse field sets.
     *
     * @return $this
     */
    public function notSparseField(): self
    {
        $this->sparseField = false;

        return $this;
    }

    /**
     * Can the field be listed in sparse field sets?
     *
     * @return bool
     */
    public function isSparseField(): bool
    {
        return true === $this->sparseField;
    }
}
