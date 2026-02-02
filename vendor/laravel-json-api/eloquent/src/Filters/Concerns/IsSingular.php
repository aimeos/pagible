<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters\Concerns;

trait IsSingular
{

    /**
     * @var bool
     */
    private bool $singular = false;

    /**
     * @return $this
     */
    public function singular(): self
    {
        $this->singular = true;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSingular(): bool
    {
        return $this->singular;
    }
}
