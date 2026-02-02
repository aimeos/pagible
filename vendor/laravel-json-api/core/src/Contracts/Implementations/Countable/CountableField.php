<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Implementations\Countable;

interface CountableField
{

    /**
     * Is the relation countable?
     *
     * @return bool
     */
    public function isCountable(): bool;
}
