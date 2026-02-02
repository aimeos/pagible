<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Pagination\Cursor;

use InvalidArgumentException;

final readonly class Cursor
{
   /**
     * Cursor constructor.
     *
     * @param string|null $before
     * @param string|null $after
     * @param int|null $limit
     */
    public function __construct(
        private ?string $before = null,
        private ?string $after = null,
        private ?int $limit = null
    ) {
        if (is_int($this->limit) && 1 > $this->limit) {
            throw new InvalidArgumentException('Expecting a limit that is 1 or greater.');
        }
    }

    /**
     * @return bool
     */
    public function isBefore(): bool
    {
        return !is_null($this->before);
    }

    /**
     * @return string|null
     */
    public function getBefore(): ?string
    {
        return $this->before;
    }

    /**
     * @return bool
     */
    public function isAfter(): bool
    {
        return !is_null($this->after) && !$this->isBefore();
    }

    /**
     * @return string|null
     */
    public function getAfter(): ?string
    {
        return $this->after;
    }

    /**
     * Set a limit, if no limit is set on the cursor.
     *
     * @param int $limit
     * @return Cursor
     */
    public function withDefaultLimit(int $limit): self
    {
        if ($this->limit === null) {
            return new self(
                before: $this->before,
                after: $this->after,
                limit: $limit,
            );
        }

        return $this;
    }

    /**
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
