<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources;

use Countable;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Pagination\Page as PageContract;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Pagination\Page;
use LaravelJsonApi\Core\Query\QueryParameters;
use LaravelJsonApi\Core\Responses\Internal\PaginatedResourceResponse;
use LaravelJsonApi\Core\Responses\Internal\ResourceCollectionResponse;
use Traversable;
use function count;

class ResourceCollection implements Responsable, IteratorAggregate, Countable
{

    /**
     * @var iterable|PageContract
     */
    public iterable $resources;

    /**
     * @var bool
     */
    protected bool $preserveAllQueryParameters = false;

    /**
     * @var QueryParameters|null
     */
    protected ?QueryParameters $queryParameters = null;

    /**
     * ResourceCollection constructor.
     *
     * @param iterable $resources
     */
    public function __construct(iterable $resources)
    {
        $this->resources = $resources;
    }

    /**
     * Indicate that all current query parameters should be appended to pagination links.
     *
     * @return $this
     */
    public function preserveQuery(): self
    {
        $this->preserveAllQueryParameters = true;

        return $this;
    }

    /**
     * Specify the query string parameters that should be present on pagination links.
     *
     * @param mixed $query
     * @return $this
     */
    public function withQuery($query): self
    {
        $this->preserveAllQueryParameters = false;
        $this->queryParameters = QueryParameters::cast($query);

        return $this;
    }

    /**
     * @return array
     */
    public function meta(): array
    {
        return [];
    }

    /**
     * @return Links
     */
    public function links(): Links
    {
        return new Links();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->resources;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->resources);
    }

    /**
     * @return bool
     */
    public function isPaginated(): bool
    {
        if ($this->resources instanceof PageContract) {
            return true;
        }

        return $this->resources instanceof Paginator;
    }

    /**
     * @param Request $request
     * @return ResourceCollectionResponse
     */
    public function prepareResponse($request): ResourceCollectionResponse
    {
        if ($this->isPaginated()) {
            return $this->preparePaginationResponse($request);
        }

        return new ResourceCollectionResponse($this);
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this->prepareResponse($request)->toResponse($request);
    }

    /**
     * @param Request $request
     * @return ResourceCollectionResponse
     */
    protected function preparePaginationResponse($request): ResourceCollectionResponse
    {
        /** Ensure the resources are a JSON:API page. */
        $this->resources = Page::cast($this->resources);

        if ($this->preserveAllQueryParameters) {
            $this->resources->withQuery($request->query());
        } else if ($this->queryParameters) {
            $this->resources->withQuery($this->queryParameters);
        }

        return new PaginatedResourceResponse($this);
    }

}
