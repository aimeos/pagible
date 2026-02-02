<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Contracts;

use LaravelJsonApi\Contracts\Implementations\Countable\CountableField;

interface Countable extends CountableField
{

    /**
     * Set the countable flag on the relationship.
     *
     * @param bool $countable
     * @return $this
     */
    public function countable(bool $countable): self;

    /**
     * Should the relationship count be loaded for top-level meta in a relationship endpoint?
     *
     * @return bool
     */
    public function isCountableInRelationship(): bool;

    /**
     * Get the name to use when calling `withCount`.
     *
     * @return string
     */
    public function withCountName(): string;

    /**
     * Get the key to use to retrieve the count value from the model.
     *
     * @return string
     */
    public function keyForCount(): string;
}
