<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Hydrators;

use Illuminate\Database\Eloquent\Model;
use LaravelJsonApi\Contracts\Store\ToOneBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\FillableToOne;
use LaravelJsonApi\Eloquent\Fields\Relations\MorphTo;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LaravelJsonApi\Eloquent\HasQueryParameters;
use UnexpectedValueException;

class ToOneHydrator implements ToOneBuilder
{

    use HasQueryParameters;

    /**
     * @var Model
     */
    private Model $model;

    /**
     * @var ToOne|FillableToOne
     */
    private ToOne $relation;

    /**
     * ToOneHydrator constructor.
     *
     * @param Model $model
     * @param ToOne $relation
     */
    public function __construct(Model $model, ToOne $relation)
    {
        if (!$relation instanceof FillableToOne) {
            throw new UnexpectedValueException(sprintf(
                'Relation %s cannot be hydrated.',
                Str::dasherize(class_basename($relation))
            ));
        }

        $this->model = $model;
        $this->relation = $relation;
        $this->queryParameters = new ExtendedQueryParameters();
    }

    /**
     * @inheritDoc
     */
    public function associate(?array $identifier): ?object
    {
        $related = $this->model->getConnection()->transaction(
            fn() => $this->relation->associate($this->model, $identifier)
        );

        return $this->relation->parse(
            $this->prepareResult($related)
        );
    }

    /**
     * Prepare the related model.
     *
     * We always do eager loading, in case any default eager load paths
     * have been set on the schema.
     *
     * @param Model|null $related
     * @return Model|null
     */
    private function prepareResult(?Model $related): ?Model
    {
        if (is_null($related)) {
            return null;
        }

        $parameters = $this->queryParameters;

        if ($this->relation instanceof MorphTo) {
            $schema = $this->relation->schemaFor($related);
            $parameters = $parameters->forSchema($schema);
        } else {
            $schema = $this->relation->schema();
        }

        $schema
            ->loaderFor($related)
            ->loadMissing($parameters->includePaths())
            ->loadCount($parameters->countable());

        return $related;
    }

}
