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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use LaravelJsonApi\Contracts\Implementations\Countable\CountableField;
use LaravelJsonApi\Contracts\Implementations\Countable\CountableSchema;
use LaravelJsonApi\Core\Schema\Schema as BaseSchema;
use LaravelJsonApi\Eloquent\Contracts\Driver;
use LaravelJsonApi\Eloquent\Contracts\EagerLoadableField;
use LaravelJsonApi\Eloquent\Contracts\Parser;
use LaravelJsonApi\Eloquent\Drivers\StandardDriver;
use LaravelJsonApi\Eloquent\Fields\Relations\ToMany;
use LaravelJsonApi\Eloquent\Fields\Relations\ToOne;
use LaravelJsonApi\Eloquent\Parsers\StandardParser;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;
use LaravelJsonApi\Eloquent\QueryBuilder\ModelLoader;
use LogicException;
use function sprintf;

abstract class Schema extends BaseSchema implements CountableSchema
{

    /**
     * The relationships that should always be eager loaded.
     *
     * @var array
     */
    protected array $with = [];

    /**
     * The default paging parameters to use if the client supplies none.
     *
     * @var array|null
     */
    protected ?array $defaultPagination = null;

    /**
     * The default sort order for this resource.
     *
     * @var string|string[]|null
     */
    protected $defaultSort = null;

    /**
     * The cached parser instance.
     *
     * @var Parser|null
     */
    protected ?Parser $parser = null;

    /**
     * The relationships that should always be eager loaded for attribute values.
     *
     * @var array|null
     */
    private ?array $defaultEagerLoadPaths = null;

    /**
     * @var string|null
     */
    private ?string $idColumn = null;

    /**
     * @inheritDoc
     */
    public function repository(): Repository
    {
        return new Repository(
            $this,
            $this->driver(),
            $this->parser(),
        );
    }

    /**
     * @return Model
     */
    public function newInstance(): Model
    {
        $modelClass = $this->model();

        return new $modelClass;
    }

    /**
     * Does the schema handle the provided model?
     *
     * @param Model|string $model
     * @return bool
     */
    public function isModel($model): bool
    {
        $expected = $this->model();

        return ($model instanceof $expected) || $model === $expected;
    }

    /**
     * Build an index query for this resource.
     *
     * Allows the developer to implement resource specific filtering
     * when querying all resources (an "index" query). This is required
     * for authorization implementation - i.e. to remove certain resources
     * that the user is not allowed to access.
     *
     * The request will be `null` if querying the resource outside of a HTTP
     * request - for example, queued broadcasting.
     *
     * @param Request|null $request
     * @param Builder $query
     * @return Builder
     */
    public function indexQuery(?Request $request, Builder $query): Builder
    {
        return $query;
    }

    /**
     * Build a "relatable" query for this resource.
     *
     * @param Request|null $request
     * @param Relation $query
     * @return Relation
     */
    public function relatableQuery(?Request $request, Relation $query): Relation
    {
        return $query;
    }

    /**
     * Get the column for the model's JSON:API resource id.
     *
     * @return string
     */
    public function idColumn(): string
    {
        if ($this->idColumn) {
            return $this->idColumn;
        }

        if ($key = $this->idKeyName()) {
            return $this->idColumn = $key;
        }

        return $this->idColumn = $this->newInstance()->getRouteKeyName();
    }

    /**
     * Get an Eloquent belongs-to field.
     *
     * @param string $fieldName
     * @return ToOne
     */
    public function toOne(string $fieldName): ToOne
    {
        $relation = $this->relationship($fieldName);

        if ($relation instanceof ToOne) {
            return $relation;
        }

        throw new LogicException(sprintf(
            'Expecting relationships %s to be an Eloquent JSON API to-one relationship.',
            $fieldName
        ));
    }

    /**
     * Get an Eloquent has-many field.
     *
     * @param string $fieldName
     * @return ToMany
     */
    public function toMany(string $fieldName): ToMany
    {
        $relation = $this->relationship($fieldName);

        if ($relation instanceof ToMany) {
            return $relation;
        }

        throw new LogicException(sprintf(
            'Expecting relationships %s to be an Eloquent JSON API to-many relationship.',
            $fieldName
        ));
    }

    /**
     * Create a model loader for the supplied model or models.
     *
     * The model loader is a convenience class that allows us to call methods
     * such as `loadMissing` with JSON:API query parameter values, e.g.
     * include paths. The model loader converts the JSON:API parameters to
     * Eloquent equivalents (e.g. eager load paths) and then calls the relevant
     * method or methods on the model or Eloquent collection.
     *
     * @param $modelOrModels
     * @return ModelLoader
     */
    public function loaderFor($modelOrModels): ModelLoader
    {
        return new ModelLoader(
            $this->server->schemas(),
            $this,
            $modelOrModels,
        );
    }

    /**
     * Create a new database query.
     *
     * @param Builder|null $query
     * @return JsonApiBuilder
     */
    public function newQuery($query = null): JsonApiBuilder
    {
        return new JsonApiBuilder(
            $this->server->schemas(),
            $this,
            $query ?: $this->newInstance()->newQuery(),
        );
    }

    /**
     * The relationships that should always be eager loaded.
     *
     * @return array
     */
    public function with(): array
    {
        if (is_array($this->defaultEagerLoadPaths)) {
            return $this->defaultEagerLoadPaths;
        }

        $paths = $this->with;

        foreach ($this->attributes() as $field) {
            if ($field instanceof EagerLoadableField) {
                $paths = array_merge($paths, Arr::wrap($field->with()));
            }
        }

        return $this->defaultEagerLoadPaths = array_values(array_unique($paths));
    }

    /**
     * Get the default sort order for this resource.
     *
     * The default sort order is used if the client supplies no sort parameters.
     * Returning `null` from this method indicates that no default sort order exists
     * and resource should be returned in the order they are retrieved from the database.
     *
     * @return string|string[]|null
     */
    public function defaultSort()
    {
        return $this->defaultSort;
    }

    /**
     * Get the default pagination parameters.
     *
     * The default pagination parameters are used if the client supplies no page
     * parameters. Returning `null` from this method indicates that there
     * should be no default pagination - i.e. if the client supplies no page
     * parameters, they receive ALL resources.
     *
     * @return array|null
     */
    public function defaultPagination(): ?array
    {
        return $this->defaultPagination;
    }

    /**
     * Will the set of filters result in zero-to-one resource?
     *
     * While individual filters can be marked as singular, there may be instances
     * where the combination of filters should result in a singular response
     * (zero-to-one resource instead of zero-to-many). Developers can use this
     * hook to add complex logic for working out if a set of filters should
     * return a singular resource.
     *
     * @param array $filters
     * @return bool
     */
    public function isSingular(array $filters): bool
    {
        return false;
    }

    /**
     * Get the parser for this resource type.
     *
     * @return Parser
     */
    public function parser(): Parser
    {
        if ($this->parser) {
            return $this->parser;
        }

        return $this->parser = new StandardParser();
    }

    /**
     * @inheritDoc
     */
    public function isCountable(string $fieldName): bool
    {
        $relation = null;

        if ($this->isRelationship($fieldName)) {
            $relation = $this->relationship($fieldName);
        }

        if ($relation instanceof CountableField) {
            return $relation->isCountable();
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function countable(): iterable
    {
        foreach ($this->relationships() as $relation) {
            if ($relation instanceof CountableField && $relation->isCountable()) {
                yield $relation->name();
            }
        }
    }

    /**
     * @return Driver
     */
    protected function driver(): Driver
    {
        return new StandardDriver($this->newInstance());
    }

}
