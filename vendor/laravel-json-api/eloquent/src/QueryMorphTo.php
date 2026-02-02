<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent;

use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Contracts\Store\QueryOneBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;
use function is_null;

class QueryMorphTo implements QueryOneBuilder
{

    use HasQueryParameters;

    /**
     * @var Model
     */
    private Model $model;

    /**
     * @var MorphTo
     */
    private MorphTo $relation;

    /**
     * QueryMorphOne constructor.
     *
     * @param Model $model
     * @param MorphTo $relation
     */
    public function __construct(Model $model, MorphTo $relation)
    {
        $this->model = $model;
        $this->relation = $relation;
        $this->queryParameters = new ExtendedQueryParameters();
    }

    /**
     * @inheritDoc
     */
    public function filter(?array $filters): self
    {
        $this->queryParameters->setFilters($filters);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function first(): ?object
    {
        /** @var Model|null $related */
        $related = $this->model->{$this->relation->relationName()};
        $filters = $this->queryParameters->filter();

        /**
         * If there are no filters, we can just return the related
         * model - loading any missing relations.
         */
        if (is_null($related) || empty($filters)) {
            return $this->relation->parse(
                $this->prepareResult($related)
            );
        }

        $schema = $this->relation->schemaFor($related);

        $expected = collect($schema->filters())
            ->map(fn(Filter $filter) => $filter->key())
            ->values();

        /**
         * If there are any filters that are not valid for this schema,
         * then we know the related model cannot match the filters. So
         * in this scenario, we return `null`.
         */
        if (collect($filters)->keys()->diff($expected)->isNotEmpty()) {
            return null;
        }

        /**
         * Otherwise we need to re-query this specific model to see if
         * it matches our filters or not.
         */
        $result = $schema
            ->newQuery($related->newQuery())
            ->whereKey($related->getKey())
            ->filter($filters)
            ->first();

        return $this->relation->parse(
            $this->prepareResult($result)
        );
    }

    /**
     * Prepare the model to be returned as the result of the query.
     *
     * @param Model|null $related
     * @return Model|null
     */
    private function prepareResult(?Model $related): ?Model
    {
        if ($related) {
            $schema = $this->relation->schemaFor($related);
            $parameters = $this->queryParameters->forSchema($schema);

            $schema
                ->loaderFor($related)
                ->loadMissing($parameters->includePaths())
                ->loadCount($parameters->countable());
        }

        return $related;
    }

}
