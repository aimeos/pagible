<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\QueryBuilder;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Traits\ForwardsCalls;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Contracts\Query\QueryParameters as QueryParametersContract;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Contracts\Schema\Relation as SchemaRelation;
use LaravelJsonApi\Core\Query\Custom\CountablePaths;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Core\Query\FilterParameters;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\QueryParameters;
use LaravelJsonApi\Core\Query\RelationshipPath;
use LaravelJsonApi\Core\Query\SortField;
use LaravelJsonApi\Core\Query\SortFields;
use LaravelJsonApi\Core\Schema\IdParser;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\QueryBuilder\Aggregates\CountableLoader;
use LaravelJsonApi\Eloquent\QueryBuilder\Applicators\FilterApplicator;
use LaravelJsonApi\Eloquent\QueryBuilder\Applicators\SortApplicator;
use LaravelJsonApi\Eloquent\QueryBuilder\EagerLoading\EagerLoader;
use LaravelJsonApi\Eloquent\Schema;
use LogicException;
use function sprintf;

/**
 * Class JsonApiBuilder
 *
 * @mixin Builder
 */
class JsonApiBuilder
{

    use ForwardsCalls;

    /**
     * @var Container
     */
    private Container $schemas;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Builder|Relation
     */
    private $query;

    /**
     * @var SchemaRelation|null
     */
    private ?SchemaRelation $relation;

    /**
     * @var ExtendedQueryParameters
     */
    private ExtendedQueryParameters $parameters;

    /**
     * @var bool
     */
    private bool $singular = false;

    /**
     * @var bool
     */
    private bool $eagerLoading = false;

    /**
     * JsonApiBuilder constructor.
     *
     * @param Container $schemas
     * @param Schema $schema
     * @param Builder|Relation $query
     * @param SchemaRelation|null $relation
     */
    public function __construct(Container $schemas, Schema $schema, $query, ?SchemaRelation $relation = null)
    {
        if ($query instanceof Relation && !$relation) {
            throw new InvalidArgumentException('Expecting a schema relation when querying an Eloquent relation.');
        }

        if ($relation && !$query instanceof Relation) {
            throw new InvalidArgumentException('Expecting an Eloquent relation when querying a schema relation.');
        }

        $this->schemas = $schemas;
        $this->schema = $schema;
        $this->query = $query;
        $this->relation = $relation;
        $this->parameters = new ExtendedQueryParameters();
    }

    /**
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function __call($name, $arguments)
    {
        $result = $this->forwardCallTo($this->query, $name, $arguments);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Apply the provide query parameters.
     *
     * @param QueryParametersContract $query
     * @return $this
     */
    public function withQueryParameters(QueryParametersContract $query): self
    {
        $query = ExtendedQueryParameters::cast($query);

        $this->filter($query->filter())
            ->sortWithDefault($query->sortFields())
            ->sparseFieldSets($query->sparseFieldSets())
            ->with($query->includePaths())
            ->withCount($query->countable());

        return $this;
    }

    /**
     * Apply the supplied JSON:API filters.
     *
     * @param FilterParameters|array|mixed|null $filters
     * @return $this
     */
    public function filter($filters): self
    {
        $applicator = FilterApplicator::make($this->schema, $this->relation)
            ->apply($this->query, $filters);

        $this->parameters->setFilters($applicator->applied());
        $this->singular = $applicator->didApplySingularFilter();

        return $this;
    }

    /**
     * Set the sparse field sets for the query.
     *
     * @param mixed $fields
     * @return $this
     * @todo in a future version, these need to be used to determine which columns to retrieve from the db.
     */
    public function sparseFieldSets($fields): self
    {
        $this->parameters->setSparseFieldSets($fields);

        return $this;
    }

    /**
     * Sort models using JSON:API sort fields.
     *
     * @param SortFields|SortField|array|string|null $fields
     * @return $this
     */
    public function sort($fields): self
    {
        $applicator = SortApplicator::make($this->schema)
            ->apply($this->query, $fields);

        $this->parameters->setSortFields($applicator->applied());

        return $this;
    }

    /**
     * Sort models using JSON:API sort fields, or use the schema's default sort order.
     *
     * @param SortFields|SortField|array|string|null $fields
     * @return $this
     */
    public function sortWithDefault($fields): self
    {
        if (null === $fields) {
            $fields = $this->schema->defaultSort();
        }

        return $this->sort($fields);
    }

    /**
     * Set the relations that should be eager loaded using JSON:API include paths.
     *
     * @param IncludePaths|RelationshipPath|array|string|null $includePaths
     * @return $this
     */
    public function with($includePaths): self
    {
        $includePaths = IncludePaths::nullable($includePaths);

        $loader = new EagerLoader(
            $this->schemas,
            $this->schema,
            $includePaths,
        );

        $this->query->with($paths = $loader->getRelations());

        foreach ($loader->getMorphs() as $name => $map) {
            $this->query->with($name, static function(EloquentMorphTo $morphTo) use ($map) {
                $morphTo->morphWith($map);
            });
        }

        $this->eagerLoading = (!empty($paths) || !empty($map));
        $this->parameters->setIncludePaths($includePaths);

        return $this;
    }

    /**
     * Add queries to count the provided JSON:API relations.
     *
     * @param $countable
     * @return $this
     */
    public function withCount($countable): self
    {
        $loader = new CountableLoader(
            $this->schema,
            $countable = CountablePaths::cast($countable)
        );

        $this->query->withCount($loader->getRelations());
        $this->parameters->setCountable($countable);

        return $this;
    }

    /**
     * Add a where clause using the JSON:API resource id.
     *
     * @param string|array|Arrayable $resourceId
     * @return $this
     */
    public function whereResourceId($resourceId): self
    {
        $column = $this->qualifiedIdColumn();
        $parser = IdParser::make($this->schema->id());

        if (is_string($resourceId)) {
            $this->query->where($column, '=', $parser->decodeIfMatch($resourceId));
            return $this;
        }

        if (is_array($resourceId) || $resourceId instanceof Arrayable) {
            $this->query->whereIn($column, $parser->decodeIds($resourceId));
            return $this;
        }

        throw new InvalidArgumentException('Unexpected resource id value.');
    }

    /**
     * Add an "order by" clause to the query for the resource id column.
     *
     * @param string $direction
     * @return $this
     */
    public function orderByResourceId(string $direction = 'asc'): self
    {
        $column = $this->qualifiedIdColumn();

        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query for the resource id column.
     *
     * @return $this
     */
    public function orderByResourceIdDescending(): self
    {
        $this->orderByResourceId('desc');

        return $this;
    }

    /**
     * Has a singular filter been applied?
     *
     * @return bool
     */
    public function isSingular(): bool
    {
        return $this->singular;
    }

    /**
     * Has no singular filter been applied?
     *
     * @return bool
     */
    public function isNotSingular(): bool
    {
        return !$this->isSingular();
    }

    /**
     * Execute the query as a cursor.
     *
     * Eager loading does not work on a lazy collection. Therefore, if any include
     * paths have been used, we must call `get()` instead of `cursor()`.
     *
     * The advantage of using this method is that we get the memory performance
     * benefit if the client has not requested any eager loading.
     *
     * @return LazyCollection
     */
    public function cursor(): LazyCollection
    {
        if ($this->eagerLoading) {
            return new LazyCollection($this->get());
        }

        return $this->query->cursor();
    }

    /**
     * Return a page of models using JSON:API page parameters.
     *
     * @param array $page
     * @return Page|mixed
     */
    public function paginate(array $page)
    {
        $paginator = $this->schema->pagination();

        if ($paginator instanceof Paginator) {
            return $paginator
                ->withKeyName($this->schema->idColumn())
                ->paginate($this->query, $page)
                ->withQuery($this->parameters->setPagination($page)->toArray());
        }

        if ($paginator) {
            throw new LogicException(sprintf(
                'Expecting paginator for resource %s to be an Eloquent paginator.',
                $this->schema->type()
            ));
        }

        throw new LogicException(sprintf(
            'Resource %s does not support pagination.',
            $this->schema->type()
        ));
    }

    /**
     * @return QueryParameters
     */
    public function getQueryParameters(): QueryParameters
    {
        return $this->parameters;
    }

    /**
     * @return Builder
     */
    public function toBase(): Builder
    {
        if ($this->query instanceof Relation) {
            return $this->query->getQuery();
        }

        return $this->query;
    }

    /**
     * Get the qualified id column.
     *
     * @return string
     */
    private function qualifiedIdColumn(): string
    {
        return $this->query->getModel()->qualifyColumn(
            $this->schema->idColumn()
        );
    }
}
