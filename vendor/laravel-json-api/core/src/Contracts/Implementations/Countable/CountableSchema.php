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

interface CountableSchema
{

    /**
     * Is the provided field name a countable field?
     *
     * @param string $fieldName
     * @return bool
     */
    public function isCountable(string $fieldName): bool;

    /**
     * Get the countable field names.
     *
     * @return iterable
     */
    public function countable(): iterable;
}
