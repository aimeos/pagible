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
use InvalidArgumentException;
use LaravelJsonApi\Core\Support\Arr;
use Traversable;
use function asort;
use function iterator_to_array;
use function ksort;

trait Hashable
{

    /**
     * @var array
     */
    private array $value = [];

    /**
     * @var callable|null
     */
    private $serializer;

    /**
     * Flags for sorting by keys.
     *
     * @var int|null
     */
    private ?int $sortKeys = null;

    /**
     * Flags for sorting by values.
     *
     * @var int|null
     */
    private ?int $sortValues = null;

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
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->value);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Mark the hash as having camel-case keys.
     *
     * @return $this
     */
    public function camelize(): self
    {
        return $this->serializeUsing(
            static fn(array $values) => Arr::camelize($values)
        );
    }

    /**
     * Mark the hash as having dash-case keys.
     *
     * @return $this
     */
    public function dasherize(): self
    {
        return $this->serializeUsing(
            static fn(array $values) => Arr::dasherize($values)
        );
    }

    /**
     * Mark the hash as having snake-case keys.
     *
     * We use underscore because this correctly converts both camel and
     * dash case keys to snake case.
     *
     * @return $this
     */
    public function snake(): self
    {
        return $this->underscore();
    }

    /**
     * Mark the hash as having underscored (snake case) keys.
     *
     * @return $this
     */
    public function underscore(): self
    {
        return $this->serializeUsing(
            static fn(array $values) => Arr::underscore($values)
        );
    }

    /**
     * Mark the hash as using the provided case for keys.
     *
     * @param string|null $case
     * @return $this
     */
    public function useCase(?string $case): self
    {
        switch ($case) {
            case 'snake' :
                $this->snake();
                break;

            case 'underscore' :
                $this->underscore();
                break;

            case 'dash' :
            case 'dasherize' :
                $this->dasherize();
                break;

            case 'camel' :
            case 'camelize' :
                $this->camelize();
                break;

            case null :
                $this->serializer = null;
                break;

            default :
                throw new InvalidArgumentException('Unexpected case.');
        }

        return $this;
    }

    /**
     * Use the callback to serialize the array.
     *
     * Serialization is deferred until when the hash is traversed.
     * This ensures that the cost of serialization is only incurred
     * if the hash is iterated.
     *
     * @param callable $callback
     * @return $this
     */
    public function serializeUsing(callable $callback): self
    {
        $this->serializer = $callback;

        return $this;
    }

    /**
     * Iterate through values in a sorted order.
     *
     * @param int $flags
     * @return $this
     * @see asort
     */
    public function sorted(int $flags = SORT_REGULAR): self
    {
        $this->sortValues = $flags;
        $this->sortKeys = null;

        return $this;
    }

    /**
     * Iterate through values in a sorted order, if sorting flags are provided.
     *
     * @param int|null $flags
     * @return $this
     */
    public function maybeSorted(?int $flags): self
    {
        if (is_int($flags)) {
            return $this->sorted($flags);
        }

        return $this;
    }

    /**
     * Iterate through field names in a sorted order.
     *
     * Sorting is deferred until when the hash is traversed.
     * This ensures that the cost of sorting keys is only incurred
     * if the hash is iterated.
     *
     * @param int $flags
     *      the flags to use when sorting.
     * @return $this
     * @see ksort()
     */
    public function sortKeys(int $flags = SORT_REGULAR): self
    {
        $this->sortKeys = $flags;
        $this->sortValues = null;

        return $this;
    }

    /**
     * Iterate through field names in a sorted order, if sorting flags are provided.
     *
     * @param int|null $flags
     * @return $this
     */
    public function maybeSortKeys(?int $flags): self
    {
        if (is_int($flags)) {
            return $this->sortKeys($flags);
        }

        return $this;
    }

    /**
     * Remove any sorting.
     *
     * @return $this
     */
    public function notSorted(): self
    {
        $this->sortValues = null;
        $this->sortKeys = null;

        return $this;
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
    public function jsonSerialize(): ?array
    {
        if ($this->isNotEmpty()) {
            return $this->all();
        }

        return null;
    }

    /**
     * @return Generator
     */
    public function cursor(): Generator
    {
        if ($fn = $this->serializer) {
            $values = $fn($this->value);
        } else {
            $values = $this->value;
        }

        if (is_int($this->sortKeys)) {
            ksort($values, $this->sortKeys);
        } else if (is_int($this->sortValues)) {
            asort($values, $this->sortValues);
        }

        yield from $values;
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

}
