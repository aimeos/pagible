<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query\Custom;

use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Implementations\Countable\CountableSchema;
use LaravelJsonApi\Contracts\Schema\Schema;
use Traversable;
use UnexpectedValueException;
use function is_array;
use function is_null;
use function is_string;

class CountablePaths implements IteratorAggregate, Countable, Arrayable
{

    /**
     * Create a new countable paths value object.
     *
     * @param mixed $value
     * @return static
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        if (is_array($value) || $value instanceof Enumerable) {
            return self::fromArray($value);
        }

        if (is_null($value)) {
            return new self();
        }

        throw new UnexpectedValueException('Unable to cast provided value to countable paths.');
    }

    /**
     * @param $paths
     * @return static
     */
    public static function fromArray($paths): self
    {
        if ($paths instanceof Enumerable) {
            $paths = $paths->all();
        }

        if (is_array($paths)) {
            return new self(...$paths);
        }

        throw new UnexpectedValueException('Expecting an array or enumerable.');
    }

    /**
     * @param string $paths
     * @return static
     */
    public static function fromString(string $paths): self
    {
        if (empty($paths)) {
            return new self();
        }

        return new self(...explode(',', $paths));
    }

    /**
     * @param mixed|null $value
     * @return static|null
     */
    public static function nullable($value): ?self
    {
        if (!is_null($value)) {
            return self::cast($value);
        }

        return null;
    }

    /**
     * @var string[]
     */
    private array $paths;

    /**
     * CountablePaths constructor.
     *
     * @param string ...$paths
     */
    public function __construct(string ...$paths)
    {
        $this->paths = $paths;
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
        return implode(',', $this->paths);
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->paths);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return string[]
     */
    public function all(): array
    {
        return $this->paths;
    }

    /**
     * @return Collection
     */
    public function collect(): Collection
    {
        return Collection::make($this->paths);
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function filter(callable $callback): self
    {
        return new self(
            ...collect($this->paths)->filter($callback)
        );
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function reject(callable $callback): self
    {
        return new self(
            ...collect($this->paths)->reject($callback)
        );
    }

    /**
     * @param Schema $schema
     * @return $this
     */
    public function forSchema(Schema $schema): self
    {
        return $this->filter(
            fn($name) => $this->isCountablePath($schema, $name)
        );
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->paths);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->paths;
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return $this->paths;
    }

    /**
     * Is the path a valid countable path?
     *
     * @param Schema $schema
     * @param string $path
     * @return bool
     */
    private function isCountablePath(Schema $schema, string $path): bool
    {
        if ($schema instanceof CountableSchema) {
            return $schema->isCountable($path);
        }

        return false;
    }

}
