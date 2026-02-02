<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters\Concerns;

trait HasColumn
{

    /**
     * @var string|null
     */
    private ?string $table = null;

    /**
     * @var string
     */
    private string $column;

    /**
     * @return string
     */
    public function column(): string
    {
        return $this->column;
    }

    /**
     * Force the table name when qualifying the column.
     *
     * This allows the developer to force the table that the column is qualified as.
     *
     * @param string $table
     * @return $this
     */
    public function qualifyAs(string $table): self
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the qualified column.
     *
     * @return string
     */
    protected function qualifiedColumn(): string
    {
        if ($this->table) {
            return $this->table . '.' . $this->column;
        }

        return $this->column;
    }
}
