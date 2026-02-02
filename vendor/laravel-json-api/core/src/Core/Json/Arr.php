<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Json;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use function array_values;

class Arr implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{

    use Concerns\ArrayList;

    /**
     * Arr constructor.
     *
     * @param array $value
     */
    public function __construct(array $value)
    {
        $this->value = $value;
    }

    /**
     * Add values to the start of the array.
     *
     * @param mixed ...$values
     * @return $this
     */
    public function prepend(...$values): self
    {
        $this->value = $values + array_values($this->value);

        return $this;
    }

    /**
     * Push values onto the end of the array.
     *
     * @param mixed ...$values
     * @return $this
     */
    public function push(...$values): self
    {
        $this->value = array_values($this->value) + $values;

        return $this;
    }
}
