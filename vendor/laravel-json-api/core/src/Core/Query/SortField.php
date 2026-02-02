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

use Illuminate\Support\Str;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Schema\Schema;
use UnexpectedValueException;
use function is_string;

class SortField
{

    /**
     * @var string
     */
    private string $name;

    /**
     * @var bool
     */
    private bool $ascending;

    /**
     * @param SortField|string $value
     * @return static
     */
    public static function cast($value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (is_string($value) && !empty($value)) {
            return self::fromString($value);
        }

        throw new UnexpectedValueException('Unexpected sort field value.');
    }

    /**
     * @param string $value
     * @return SortField
     */
    public static function fromString(string $value): self
    {
        if (Str::startsWith($value, '-')) {
            return new self(ltrim($value, '-'), false);
        }

        return new self($value);
    }

    /**
     * Create a new ascending sort field.
     *
     * @param string $name
     * @return static
     */
    public static function ascending(string $name): self
    {
        return new self($name);
    }

    /**
     * Create a new descending sort field.
     *
     * @param string $name
     * @return static
     */
    public static function descending(string $name): self
    {
        return new self($name, false);
    }

    /**
     * SortField constructor.
     *
     * @param string $name
     * @param bool $ascending
     */
    public function __construct(string $name, bool $ascending = true)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->name = $name;
        $this->ascending = $ascending;
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
        if ($this->isAscending()) {
            return $this->name;
        }

        return "-{$this->name}";
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isAscending(): bool
    {
        return !$this->isDescending();
    }

    /**
     * @return bool
     */
    public function isDescending(): bool
    {
        return false === $this->ascending;
    }

    /**
     * Get the sort direction as a string.
     *
     * @return string
     */
    public function getDirection(): string
    {
        return $this->isAscending() ? 'asc' : 'desc';
    }

    /**
     * Does the sort field exist on the provided schema?
     *
     * @param Schema $schema
     * @return bool
     */
    public function existsOnSchema(Schema $schema): bool
    {
        return $schema->isSortField($this->name());
    }
}
