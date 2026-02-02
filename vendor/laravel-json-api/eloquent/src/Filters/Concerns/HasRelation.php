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

use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Schema;

trait HasRelation
{
    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * The JSON:API relationship field name.
     *
     * @var string
     */
    private string $fieldName;

    /**
     * The JSON:API filter name.
     *
     * @var string|null
     */
    private ?string $key;

    /**
     * @var Relation|null
     */
    private ?Relation $relation = null;

    /**
     * Get the JSON:API key for the filter.
     *
     * @return string
     */
    public function key(): string
    {
        return $this->key ?? $this->fieldName();
    }

    /**
     * Get the JSON:API relationship field name.
     *
     * @return string
     */
    public function fieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Get the Eloquent relation name.
     *
     * @return string
     */
    public function relationName(): string
    {
        return $this->relation()->relationName();
    }

    /**
     * Get the relationship used for this filter.
     *
     * @return Relation
     */
    protected function relation(): Relation
    {
        if ($this->relation) {
            return $this->relation;
        }

        return $this->relation = $this->schema->relationship($this->fieldName);
    }
}
