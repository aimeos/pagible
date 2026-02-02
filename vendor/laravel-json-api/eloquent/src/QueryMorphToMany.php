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

use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\Relation;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LogicException;

class QueryMorphToMany implements QueryManyBuilder, IteratorAggregate
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
     * @var MorphToMany
     */
    private MorphToMany $relation;

    /**
     * QueryMorphToMany constructor.
     *
     * @param Schema $schema
     * @param Model $model
     * @param MorphToMany $relation
     */
    public function __construct(Schema $schema, Model $model, MorphToMany $relation)
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
    public function get(): Collection
    {
        return Collection::make($this->values());
    }

    /**
     * @return LazyCollection
     */
    public function cursor(): LazyCollection
    {
        return LazyCollection::make(function () {
            yield from $this->values();
        });
    }

    /**
     * @return Generator
     */
    public function getIterator(): Generator
    {
        foreach ($this->relation as $relation) {
            $query = $this->toQuery($relation);

            if ($this->request) {
                $query->withRequest($this->request);
            }

            yield $query->withQuery(
                $this->queryParameters->forSchema($relation->schema())
            );
        }
    }

    /**
     * @param Relation $relation
     * @return QueryMorphTo|QueryToMany|QueryToOne
     */
    private function toQuery(Relation $relation)
    {
        if ($relation instanceof MorphTo) {
            return new QueryMorphTo($this->model, $relation);
        }

        if ($relation instanceof ToOne) {
            return new QueryToOne($this->model, $relation);
        }

        if ($relation instanceof ToMany) {
            return new QueryToMany($this->schema, $this->model, $relation);
        }

        throw new LogicException(sprintf(
            'Unsupported relation for querying morph-to-many: %s',
            get_class($relation),
        ));
    }

    /**
     * @return Generator
     */
    private function values(): Generator
    {
        /** @var QueryToOne|QueryMorphTo|QueryToMany $query */
        foreach ($this as $query) {
            if ($query instanceof QueryToOne || $query instanceof QueryMorphTo) {
                if ($value = $query->first()) {
                    yield $value;
                }
                continue;
            }

            foreach ($query->cursor() as $value) {
                yield $value;
            }
        }
    }

}
