<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\QueryBuilder\Applicators;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelJsonApi\Contracts\Schema\Relation as SchemaRelation;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Core\Query\FilterParameters;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Schema as EloquentSchema;
use RuntimeException;
use function sprintf;

class FilterApplicator
{
    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var SchemaRelation|null
     */
    private ?SchemaRelation $relation;

    /**
     * @var FilterParameters|null
     */
    private ?FilterParameters $filters = null;

    /**
     * @var bool
     */
    private bool $singular = false;

    /**
     * Create a new filter applicator.
     *
     * @param Schema $schema
     * @param SchemaRelation|null $relation
     * @return static
     */
    public static function make(Schema $schema, ?SchemaRelation $relation = null): self
    {
        return new self($schema, $relation);
    }

    /**
     * @param Schema $schema
     * @param SchemaRelation|null $relation
     */
    public function __construct(Schema $schema, ?SchemaRelation $relation)
    {
        $this->schema = $schema;
        $this->relation = $relation;
    }

    /**
     * Apply the supplied JSON:API filters to the provided query.
     *
     * @param Builder|Relation $query
     * @param FilterParameters|array|mixed|null $filters
     * @return $this
     */
    public function apply($query, $filters): self
    {
        $filters = $this->filters = FilterParameters::nullable($filters);

        if (null === $filters || $filters->isEmpty()) {
            return $this;
        }

        $keys = [];

        foreach ($this->cursor() as $filter) {
            if ($filter instanceof Filter) {
                $keys[] = $key = $filter->key();

                if ($filters->exists($key)) {
                    $filter->apply($query, $filters->get($key)->value());

                    if ($filter->isSingular()) {
                        $this->singular = true;
                    }
                }
                continue;
            }

            throw new RuntimeException(sprintf(
                'Schema %s has a filter that does not implement the Eloquent filter contract.',
                $this->schema->type()
            ));
        }

        $this->rejectUnrecognised($filters, $keys);

        if (false === $this->singular && $this->schema instanceof EloquentSchema) {
            $this->singular = $this->schema->isSingular($filters->toArray());
        }

        return $this;
    }

    /**
     * Get the applied filters.
     *
     * @return FilterParameters|null
     */
    public function applied(): ?FilterParameters
    {
        return $this->filters;
    }

    /**
     * Were any singular filters applied?
     *
     * @return bool
     */
    public function didApplySingularFilter(): bool
    {
        return $this->singular;
    }

    /**
     * Throw an exception if any filters were unrecognised.
     *
     * @param FilterParameters $filters
     * @param array $allowedFilterKeys
     * @return void
     */
    private function rejectUnrecognised(FilterParameters $filters, array $allowedFilterKeys): void
    {
        $unrecognised = $filters
            ->collect()
            ->keys()
            ->diff($allowedFilterKeys);

        if ($unrecognised->isNotEmpty() && $this->relation) {
            throw new RuntimeException(sprintf(
                'Encountered filters that are not defined on the %s schema or %s relation: %s',
                $this->schema->type(),
                $this->relation->name(),
                $unrecognised->implode(', ')
            ));
        }

        if ($unrecognised->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Encountered filters that are not defined on the %s schema: %s',
                $this->schema->type(),
                $unrecognised->implode(', ')
            ));
        }
    }

    /**
     * @return iterable
     */
    private function cursor(): iterable
    {
        foreach ($this->schema->filters() as $filter) {
            yield $filter;
        }

        if ($this->relation) {
            foreach ($this->relation->filters() as $filter) {
                yield $filter;
            }
        }
    }
}
