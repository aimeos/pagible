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
use InvalidArgumentException;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Schema\Schema;
use Traversable;
use UnexpectedValueException;
use function collect;
use function explode;
use function implode;
use function is_string;

class RelationshipPath implements IteratorAggregate, Countable
{

    /**
     * @var string[]
     */
    private array $names;

    /**
     * @param RelationshipPath|string $value
     * @return RelationshipPath
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value)) {
            return self::fromString($value);
        }

        throw new UnexpectedValueException('Unexpected relationship path value.');
    }

    /**
     * @param string $path
     * @return RelationshipPath
     */
    public static function fromString(string $path): self
    {
        if (!empty($path)) {
            return new self(...explode('.', $path));
        }

        throw new UnexpectedValueException('Expecting a non-empty string.');
    }

    /**
     * IncludePath constructor.
     *
     * @param string ...$paths
     */
    public function __construct(string ...$paths)
    {
        if (empty($paths)) {
            throw new InvalidArgumentException('Expecting at least one relationship path.');
        }

        $this->names = $paths;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * Fluent to string method.
     *
     * @return string
     */
    public function toString(): string
    {
        return implode('.', $this->names);
    }

    /**
     * @return array
     */
    public function names(): array
    {
        return $this->names;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->names;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->names);
    }

    /**
     * Get the first name.
     *
     * @return string
     */
    public function first(): string
    {
        return $this->names[0];
    }

    /**
     * @param int $num
     * @return $this
     */
    public function take(int $num): self
    {
        return new self(...collect($this->names)->take($num));
    }

    /**
     * Does the path exist on the provided schema?
     *
     * @param Schema $schema
     * @return bool
     */
    public function existsOnSchema(Schema $schema): bool
    {
        if ($schema->isRelationship($first = $this->first())) {
            return $schema->relationship($first)->isIncludePath();
        }

        return false;
    }

    /**
     * @param int $num
     * @return $this|null
     */
    public function skip(int $num): ?self
    {
        $names = collect($this->names)->skip($num);

        if ($names->isNotEmpty()) {
            return new self(...$names);
        }

        return null;
    }

}
