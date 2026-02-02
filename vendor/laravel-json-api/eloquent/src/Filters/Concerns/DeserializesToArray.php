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

use Illuminate\Support\Enumerable;
use UnexpectedValueException;
use function is_array;

trait DeserializesToArray
{
    use DeserializesValue;

    /**
     * @param mixed $value
     * @return array
     */
    protected function toArray($value): array
    {
        $values = $this->deserialize($value);

        if ($values instanceof Enumerable) {
            return $values->all();
        }

        if (is_array($values) || null === $values) {
            return $values ?? [];
        }

        throw new UnexpectedValueException('Expecting filter value to deserialize to an array.');
    }
}
