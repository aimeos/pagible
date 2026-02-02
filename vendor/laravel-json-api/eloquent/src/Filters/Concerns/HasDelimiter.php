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

use InvalidArgumentException;
use LogicException;
use function explode;
use function is_array;
use function is_string;

trait HasDelimiter
{

    /**
     * @var string|null
     */
    private ?string $delimiter = null;

    /**
     * If the filter accepts a string value, the delimiter to use to extract values.
     *
     * @param string $delimiter
     * @return $this
     */
    public function delimiter(string $delimiter): self
    {
        if (empty($delimiter)) {
            throw new InvalidArgumentException('Expecting a non-empty string delimiter.');
        }

        $this->delimiter = $delimiter;

        return $this;
    }

    /**
     * Convert the provided value to an array.
     *
     * @param string|array|null $value
     * @return array
     */
    protected function toArray($value): array
    {
        if ($this->delimiter && is_string($value)) {
            return ('' !== $value) ? explode($this->delimiter, $value) : [];
        }

        if (is_array($value) || null === $value) {
            return $value ?? [];
        }

        throw new LogicException('Expecting filter value to be an array, or a string when a string delimiter is set.');
    }
}
