<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Json\Concerns;

use Generator;
use LaravelJsonApi\Core\Support\Arr;
use LogicException;
use Traversable;
use function iterator_to_array;

trait ArrayList
{

    /**
     * @var array
     */
    private array $value = [];

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return Arr::exists($this->value, $offset);
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->value[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->next() !== intval($offset)) {
            throw new LogicException('Can only set the next element in the array list.');
        }

        $this->value[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        Arr::forget($this->value, $offset);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->value);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->value;
    }

    /**
     * @inheritDoc
     */
    public function cursor(): Generator
    {
        foreach ($this->value as $value) {
            yield $value;
        }
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return iterator_to_array($this->cursor());
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->cursor();
    }

    /**
     * Get the next index.
     *
     * @return int
     */
    public function next(): int
    {
        return $this->count() + 1;
    }
}
