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

use Illuminate\Support\LazyCollection;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Contracts\Store\HasPagination;
use LaravelJsonApi\Contracts\Store\HasSingularFilters;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;
use LaravelJsonApi\Eloquent\Contracts\Driver;
use LaravelJsonApi\Eloquent\Contracts\Parser;
use LaravelJsonApi\Eloquent\QueryBuilder\JsonApiBuilder;

class QueryAll implements QueryManyBuilder, HasPagination, HasSingularFilters
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
     * QueryAll constructor.
     *
     * @param Schema $schema
     * @param Driver $driver
     * @param Parser $parser
     */
    public function __construct(Schema $schema, Driver $driver, Parser $parser)
    {
        $this->schema = $schema;
        $this->driver = $driver;
        $this->parser = $parser;
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
     * @return JsonApiBuilder
     */
    public function query(): JsonApiBuilder
    {
        $base = $this->schema->indexQuery(
            $this->request, $this->driver->queryAll()
        );

        return $this->schema
            ->newQuery($base)
            ->withQueryParameters($this->queryParameters);
    }

    /**
     * @return object|null
     */
    public function first(): ?object
    {
        return $this->parser->parseNullable(
            $this->query()->first()
        );
    }

    /**
     * @inheritDoc
     */
    public function firstOrMany()
    {
        $query = $this->query();

        if ($query->isSingular()) {
            return $this->parser->parseNullable(
                $query->first()
            );
        }

        return $this->parser->parseMany(
            $query->cursor()
        );
    }

    /**
     * @inheritDoc
     */
    public function get(): iterable
    {
        return $this->parser->parseMany(
            $this->query()->get()
        );
    }

    /**
     * @return LazyCollection
     */
    public function cursor(): LazyCollection
    {
        $value = $this->parser->parseMany(
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
        return $this->parser->parsePage(
            $this->query()->paginate($page)
        );
    }

    /**
     * @inheritDoc
     */
    public function getOrPaginate(?array $page): iterable
    {
        $query = $this->query();

        if (is_null($page)) {
            $page = $this->schema->defaultPagination();
        }

        if (is_null($page)) {
            return $this->parser->parseMany(
                $query->get()
            );
        }

        return $this->parser->parsePage(
            $query->paginate($page)
        );
    }

    /**
     * @inheritDoc
     */
    public function firstOrPaginate(?array $page)
    {
        $query = $this->query();

        /**
         * If page is `null` and a singular filter has been used,
         * we return the first matching record as the client is expecting
         * a zero-to-one response.
         *
         * If a singular filter has been used but the page parameter
         * is not null, then the client has explicitly asked for a page so
         * we want to return a page regardless of the fact that a singular
         * filter has been used.
         */
        if (is_null($page) && $query->isSingular()) {
            return $this->parser->parseNullable(
                $query->first()
            );
        }

        $page = $page ?? $this->schema->defaultPagination();

        if (is_null($page)) {
            return $this->parser->parseMany(
                $query->cursor()
            );
        }

        return $this->parser->parsePage(
            $query->paginate($page)
        );
    }
}
