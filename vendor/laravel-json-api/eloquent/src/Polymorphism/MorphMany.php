<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Polymorphism;

use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

class MorphMany implements IteratorAggregate, \Countable
{

    /**
     * @var MorphValue[]
     */
    private array $values;

    /**
     * MorphMany constructor.
     *
     * @param MorphValue ...$values
     */
    public function __construct(MorphValue ...$values)
    {
        $this->values = $values;
    }

    /**
     * @param $relations
     * @return $this
     */
    public function load($relations): self
    {
        foreach ($this->values as $value) {
            $value->load($relations);
        }

        return $this;
    }

    /**
     * @param $relations
     * @return $this
     */
    public function loadMissing($relations): self
    {
        foreach ($this->values as $value) {
            $value->loadMissing($relations);
        }

        return $this;
    }

    /**
     * @param MorphValue $value
     * @return $this
     */
    public function push(MorphValue $value): self
    {
        $this->values[] = $value;

        return $this;
    }

    /**
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->all());
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return iterator_to_array($this);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        foreach ($this->values as $value) {
            if ($value->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->values as $value) {
            $count += $value->count();
        }

        return $count;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        foreach ($this->values as $value) {
            foreach ($value as $item) {
                yield $item;
            }
        }
    }

}
