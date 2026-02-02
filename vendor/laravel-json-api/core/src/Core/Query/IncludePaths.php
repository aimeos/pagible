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
use function collect;
use function count;
use function explode;
use function is_array;
use function is_string;

class IncludePaths implements IteratorAggregate, Countable, Arrayable
{

    /**
     * @var RelationshipPath[]
     */
    private array $stack;

    /**
     * @param IncludePaths|RelationshipPath|Enumerable|array|string|null $value
     * @return IncludePaths
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof RelationshipPath) {
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

        throw new UnexpectedValueException('Unexpected include paths value.');
    }

    /**
     * @param array|Enumerable $paths
     * @return IncludePaths
     */
    public static function fromArray($paths): self
    {
        if (!is_array($paths) && !$paths instanceof Enumerable) {
            throw new \InvalidArgumentException('Expecting an array or enumerable object.');
        }

        return new self(...collect($paths)->map(fn($path) => RelationshipPath::cast($path)));
    }

    /**
     * @param string $paths
     * @return IncludePaths
     */
    public static function fromString(string $paths): self
    {
        if (empty($paths)) {
            return new self();
        }

        return new self(...collect(explode(',', $paths))
            ->map(fn(string $path) => RelationshipPath::fromString($path))
        );
    }

    /**
     * @param IncludePaths|RelationshipPath|array|string|null $value
     * @return IncludePaths|null
     */
    public static function nullable($value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        return self::cast($value);
    }

    /**
     * IncludePaths constructor.
     *
     * @param RelationshipPath ...$paths
     */
    public function __construct(RelationshipPath ...$paths)
    {
        $this->stack = $paths;
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
     * @return RelationshipPath[]
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
     * @param int $num
     * @return $this
     */
    public function skip(int $num): self
    {
        $items = collect($this->stack)
            ->map(fn(RelationshipPath $path) => $path->skip($num))
            ->filter()
            ->values();

        return new self(...$items);
    }

    /**
     * Run a filter over each relationship path.
     *
     * @param callable $callback
     * @return $this
     */
    public function filter(callable $callback): self
    {
        return new self(
            ...$this->collect()->filter($callback)
        );
    }

    /**
     * Create new include paths of all relationship paths that do not pass a given truth test.
     *
     * @param callable $callback
     * @return $this
     */
    public function reject(callable $callback): self
    {
        return new self(
            ...$this->collect()->reject($callback)
        );
    }

    /**
     * Get the include paths that are valid for the provided schema.
     *
     * @param Schema $schema
     * @return $this
     */
    public function forSchema(Schema $schema): self
    {
        return $this->filter(
            static fn(RelationshipPath $path) => $path->existsOnSchema($schema)
        );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return collect($this->stack)->map(function (RelationshipPath $path) {
            return $path->toString();
        })->all();
    }

}
