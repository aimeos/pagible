<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters;

use Closure;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesToArray;
use LaravelJsonApi\Eloquent\Filters\Concerns\HasRelation;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;
use LaravelJsonApi\Eloquent\QueryBuilder\Applicators\FilterApplicator;
use LaravelJsonApi\Eloquent\Schema;

class WhereHas implements Filter
{
    use DeserializesToArray;
    use HasRelation;
    use IsSingular;

    /**
     * Create a new filter.
     *
     * @param Schema $schema
     * @param string $fieldName
     * @param string|null $key
     * @return static
     */
    public static function make(Schema $schema, string $fieldName, ?string $key = null)
    {
        return new static($schema, $fieldName, $key);
    }

    /**
     * WhereHas constructor.
     *
     * @param Schema $schema
     * @param string $fieldName
     * @param string|null $key
     */
    public function __construct(Schema $schema, string $fieldName, ?string $key = null)
    {
        $this->schema = $schema;
        $this->fieldName = $fieldName;
        $this->key = $key;
    }

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        return $query->whereHas(
            $this->relationName(),
            $this->callback($value),
        );
    }

    /**
     * Get the relation query callback.
     *
     * @param mixed $value
     * @return Closure
     */
    protected function callback($value): Closure
    {
        return function($query) use ($value) {
            $relation = $this->relation();
            FilterApplicator::make($relation->schema(), $relation)
                ->apply($query, $this->toArray($value));
        };
    }
}
