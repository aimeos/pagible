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
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Store\QueryOneBuilder as QueryOneBuilderContract;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Contracts\Driver;
use LaravelJsonApi\Eloquent\Contracts\Parser;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;

class QueryOne implements QueryOneBuilderContract
{

    use HasQueryParameters;

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Driver
     */
    private Driver $driver;

    /**
     * @var Parser
     */
    private Parser $parser;

    /**
     * @var Model|null
     */
    private ?Model $model;

    /**
     * @var string|null
     */
    private ?string $resourceId;

    /**
     * QueryOne constructor.
     *
     * @param Schema $schema
     * @param Driver $driver
     * @param Parser $parser
     * @param Model|string $modelOrResourceId
     */
    public function __construct(Schema $schema, Driver $driver, Parser $parser, $modelOrResourceId)
    {
        if ($modelOrResourceId instanceof Model) {
            $model = $modelOrResourceId;
            $resourceId = null;
        } else if (is_string($modelOrResourceId) && !empty($modelOrResourceId)) {
            $model = null;
            $resourceId = $modelOrResourceId;
        } else {
            throw new InvalidArgumentException('Expecting a model or non-empty string resource id.');
        }

        $this->schema = $schema;
        $this->driver = $driver;
        $this->parser = $parser;
        $this->model = $model;
        $this->resourceId = $resourceId;
        $this->queryParameters = new ExtendedQueryParameters();
    }

    /**
     * @return JsonApiBuilder
     */
    public function query(): JsonApiBuilder
    {
        $query = $this->schema->newQuery(
            $this->driver->query()
        );

        if ($this->model) {
            $query->whereKey($this->model->getKey());
        } else {
            $query->whereResourceId($this->resourceId);
        }

        return $query->withQueryParameters($this->queryParameters);
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
        if ($this->model && empty($this->queryParameters->filter())) {
            $this->schema
                ->loaderFor($this->model)
                ->loadMissing($this->queryParameters->includePaths())
                ->loadCount($this->queryParameters->countable());

            return $this->parser->parseOne($this->model);
        }

        return $this->parser->parseNullable(
            $this->query()->first()
        );
    }

}
