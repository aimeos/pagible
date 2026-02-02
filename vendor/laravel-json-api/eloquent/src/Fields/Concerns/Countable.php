<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Concerns;

use InvalidArgumentException;
use LaravelJsonApi\Core\Support\Str;
use function is_bool;

trait Countable
{

    /**
     * @var bool
     */
    private bool $countable = false;

    /**
     * @var bool|null
     */
    private ?bool $countableInRelationship = null;

    /**
     * @var string|null
     */
    private ?string $countAs = null;

    /**
     * Set the countable flag on the relationship.
     *
     * @param bool $bool
     * @return $this
     */
    public function countable(bool $bool): self
    {
        $this->countable = $bool;

        return $this;
    }

    /**
     * @return $this
     */
    public function canCount(): self
    {
        return $this->countable(true);
    }

    /**
     * @return $this
     */
    public function cannotCount(): self
    {
        return $this->countable(false);
    }

    /**
     * Mark the relation as always having a "count" in the top-level meta of a relationship endpoint.
     *
     * @return $this
     */
    public function alwaysCountInRelationship(): self
    {
        $this->countableInRelationship = true;

        return $this;
    }

    /**
     * Mark the relation as never having a "count" in the top-level meta of a relationship endpoint.
     *
     * @return $this
     */
    public function dontCountInRelationship(): self
    {
        $this->countableInRelationship = false;

        return $this;
    }

    /**
     * Set an alias for the relationship count.
     *
     * @param string $name
     * @return $this
     */
    public function countAs(string $name): self
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->countAs = $name;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isCountable(): bool
    {
        return $this->countable;
    }

    /**
     * @inheritDoc
     */
    public function isCountableInRelationship(): bool
    {
        if (!$this->isCountable()) {
            return false;
        }

        if (is_bool($this->countableInRelationship)) {
            return $this->countableInRelationship;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function withCountName(): string
    {
        if ($this->countAs) {
            return "{$this->relationName()} as {$this->countAs}";
        }

        return $this->relationName();
    }

    /**
     * @inheritDoc
     */
    public function keyForCount(): string
    {
        if ($this->countAs) {
            return $this->countAs;
        }

        return Str::snake($this->relationName()) . '_count';
    }
}
