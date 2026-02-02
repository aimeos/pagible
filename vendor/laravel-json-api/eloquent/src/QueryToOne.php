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
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use LaravelJsonApi\Contracts\Store\QueryOneBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;
use function sprintf;

class QueryToOne implements QueryOneBuilder
{

    use HasQueryParameters;

    /**
     * @var Model
     */
    private Model $model;

    /**
     * @var ToOne
     */
    private ToOne $relation;

    /**
     * QueryToOne constructor.
     *
     * @param Model $model
     * @param ToOne $relation
     */
    public function __construct(Model $model, ToOne $relation)
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
        if ($this->model->relationLoaded($this->relation->name()) && empty($this->queryParameters->filter())) {
            return $this->relation->parse(
                $this->related()
            );
        }

        return $this->relation->parse(
            $this->query()->first()
        );
    }

    /**
     * @return JsonApiBuilder
     */
    public function query(): JsonApiBuilder
    {
        return $this->relation
            ->newQuery($this->getRelation())
            ->withQueryParameters($this->queryParameters);
    }

    /**
     * @return EloquentRelation
     */
    private function getRelation(): EloquentRelation
    {
        $name = $this->relation->relationName();

        assert(method_exists($this->model, $name)  || $this->model->relationResolver($this->model::class, $name), sprintf(
            'Expecting method %s to exist on model %s',
            $name,
            $this->model::class,
        ));

        $relation = $this->model->{$name}();

        assert($relation instanceof EloquentRelation, sprintf(
            'Expecting method %s on model %s to return an Eloquent relation.',
            $name,
            $this->model::class,
        ));

        return $relation;
    }

    /**
     * Return the already loaded related model.
     *
     * @return Model|null
     */
    private function related(): ?Model
    {
        if ($related = $this->model->getRelation($this->relation->relationName())) {
            $this->relation
                ->schema()
                ->loaderFor($related)
                ->loadMissing($this->queryParameters->includePaths())
                ->loadCount($this->queryParameters->countable());

            return $related;
        }

        return null;
    }

}
