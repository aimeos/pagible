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

use LaravelJsonApi\Eloquent\Contracts\SortField;
use LaravelJsonApi\Eloquent\Schema;

class SortCountable implements SortField
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var string
     */
    private string $fieldName;

    /**
     * @var string
     */
    private string $key;

    /**
     * Create a new sortable field.
     *
     * @param Schema $schema
     * @param string $fieldName
     * @param string|null $key
     * @return static
     */
    public static function make(Schema $schema, string $fieldName, ?string $key = null): self
    {
        return new self($schema, $fieldName, $key);
    }

    /**
     * SortCount constructor.
     *
     * @param Schema $schema
     * @param string $fieldName
     * @param string|null $key
     */
    public function __construct(Schema $schema, string $fieldName, ?string $key = null)
    {
        $this->schema = $schema;
        $this->fieldName = $fieldName;
        $this->key = $key ?? $fieldName;
    }

    /**
     * @inheritDoc
     */
    public function sortField(): string
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function sort($query, string $direction = 'asc')
    {
        $relation = $this->schema->toMany($this->fieldName);

        return $query
            ->withCount($relation->withCountName())
            ->orderBy($relation->keyForCount(), $direction);
    }

}
