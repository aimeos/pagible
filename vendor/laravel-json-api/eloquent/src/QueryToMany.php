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
use Illuminate\Support\LazyCollection;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Contracts\Store\HasPagination;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;
use function sprintf;

class QueryToMany implements QueryManyBuilder, HasPagination
{

    use HasQueryParameters;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Model
     */
    private Model $model;

    /**
     * @var ToMany
     */
    private ToMany $relation;

    /**
     * QueryToMany constructor.
     *
     * @param Schema $schema
     * @param Model $model
     * @param ToMany $relation
     */
    public function __construct(Schema $schema, Model $model, ToMany $relation)
    {
        $this->schema = $schema;
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
    public function sort($fields): self
    {
        $this->queryParameters->setSortFields($fields);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get(): iterable
    {
        return $this->relation->parse(
            $this->query()->get()
        );
    }

    /**
     * @return LazyCollection
     */
    public function cursor(): LazyCollection
    {
        $value = $this->relation->parse(
            $this->query()->cursor()
        );

        if ($value instanceof LazyCollection) {
            return $value;
        }

        return LazyCollection::make($value);
    }

    /**
     * @inheritDoc
     */
    public function paginate(array $page): Page
    {
        return $this->relation->parsePage(
            $this->query()->paginate($page)
        );
    }

    /**
     * @inheritDoc
     */
    public function getOrPaginate(?array $page): iterable
    {
        if (is_null($page)) {
            $page = $this->relation->defaultPagination();
        }

        if (is_null($page)) {
            return $this->get();
        }

        return $this->paginate($page);
    }

    /**
     * @return JsonApiBuilder
     */
    public function query(): JsonApiBuilder
    {
        $this->prepareModel();

        $base = $this->relation->schema()->relatableQuery(
            $this->request, $this->getRelation()
        );

        return $this->relation
            ->newQuery($base)
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
     * @return $this
     */
    private function prepareModel(): self
    {
        if ($this->relation->isCountableInRelationship()) {
            $this->model->loadCount(
                $this->relation->withCountName(),
            );
        }

        return $this;
    }

}
