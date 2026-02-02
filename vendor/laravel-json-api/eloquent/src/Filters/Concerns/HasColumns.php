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

use Illuminate\Database\Eloquent\Model;

trait HasColumns
{
    /**
     * @var string|null
     */
    private ?string $table = null;

    /**
     * @var array<string>
     */
    private array $columns = [];

    /**
     * @return array<string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Add a column to the filter.
     *
     * @param string $column
     * @return $this
     */
    public function withColumn(string $column): static
    {
        $this->columns[] = $column;

        return $this;
    }

    /**
     * Add columns to the filter.
     *
     * @param string ...$columns
     * @return $this
     */
    public function withColumns(string ...$columns): static
    {
        $this->columns = [
            ...$this->columns,
            ...$columns,
        ];

        return $this;
    }

    /**
     * Force the table name when qualifying the columns.
     *
     * This allows the developer to force the table that the columns are qualified with.
     *
     * @param string $table
     * @return $this
     */
    public function qualifyAs(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get qualified columns.
     *
     * @return array<string>
     */
    protected function qualifiedColumns(?Model $model = null): array
    {
        if ($this->table) {
            return array_map(
                fn($column) => $this->table . '.' . $column,
                $this->columns,
            );
        }

        if ($model) {
            return array_map(
                static fn($column) => $model->qualifyColumn($column),
                $this->columns,
            );
        }

        return $this->columns;
    }
}
