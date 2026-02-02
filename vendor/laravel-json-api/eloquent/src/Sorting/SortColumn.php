<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Sorting;

use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\SortField;

class SortColumn implements SortField
{

    /**
     * @var string
     */
    private string $fieldName;

    /**
     * @var string
     */
    private string $column;

    /**
     * Create a new sortable field.
     *
     * @param string $fieldName
     * @param string|null $column
     * @return static
     */
    public static function make(string $fieldName, ?string $column = null): self
    {
        return new self($fieldName, $column);
    }

    /**
     * SortColumn constructor.
     *
     * @param string $fieldName
     * @param string|null $column
     */
    public function __construct(string $fieldName, ?string $column = null)
    {
        $this->fieldName = $fieldName;
        $this->column = $column ?? $this->guessColumn();
    }

    /**
     * @inheritDoc
     */
    public function sortField(): string
    {
        return $this->fieldName;
    }

    /**
     * @inheritDoc
     */
    public function sort($query, string $direction = 'asc')
    {
        return $query->orderBy(
            $query->getModel()->qualifyColumn($this->column),
            $direction,
        );
    }

    /**
     * @return string
     */
    private function guessColumn(): string
    {
        return Str::underscore($this->fieldName);
    }
}
