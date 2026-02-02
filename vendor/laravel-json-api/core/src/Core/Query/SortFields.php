<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Schema\Schema;
use Traversable;
use UnexpectedValueException;
use function array_map;
use function collect;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_string;

class SortFields implements IteratorAggregate, Countable, Arrayable
{

    /**
     * @var SortField[]
     */
    private array $stack;

    /**
     * @param SortFields|SortField|Enumerable|array|string|null $value
     * @return SortFields
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof SortField) {
            return new self($value);
        }

        if (is_array($value) || $value instanceof Enumerable) {
            return self::fromArray($value);
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_null($value)) {
            return new self();
        }

        throw new UnexpectedValueException('Unexpected sort fields value.');
    }

    /**
     * @param array|Enumerable $values
     * @return SortFields
     */
    public static function fromArray($values): self
    {
        if (!is_array($values) && !$values instanceof Enumerable) {
            throw new \InvalidArgumentException('Expecting an array or enumerable object.');
        }

        return new self(...collect($values)
            ->map(fn($field) => SortField::cast($field))
        );
    }

    /**
     * @param string $value
     * @return SortFields
     */
    public static function fromString(string $value): self
    {
        if (empty($value)) {
            return new self();
        }

        return new self(...collect(explode(',', $value))
            ->map(fn($field) => SortField::fromString($field))
        );
    }

    /**
     * @param SortFields|SortField|array|string|null $value
     * @return SortFields|null
     */
    public static function nullable($value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        return self::cast($value);
    }

    /**
     * SortFields constructor.
     *
     * @param SortField ...$fields
     */
    public function __construct(SortField ...$fields)
    {
        $this->stack = $fields;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return implode(',', $this->stack);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return SortField[]
     */
    public function all(): array
    {
        return $this->stack;
    }

    /**
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->stack);
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function filter(callable $callback): self
    {
        $copy = new self();
        $copy->stack = collect($this->stack)->filter($callback)->all();

        return $copy;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function reject(callable $callback): self
    {
        $copy = new self();
        $copy->stack = collect($this->stack)->reject($callback)->all();

        return $copy;
    }

    /**
     * @param Schema $schema
     * @return $this
     */
    public function forSchema(Schema $schema): self
    {
        return $this->filter(
            static fn(SortField $field) => $field->existsOnSchema($schema)
        );
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->stack;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return array_map(function (SortField $field) {
            return $field->toString();
        }, $this->stack);
    }

}
