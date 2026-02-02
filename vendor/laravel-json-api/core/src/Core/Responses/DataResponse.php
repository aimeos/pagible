<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Responses;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Core\Resources\ResourceCollection;
use LaravelJsonApi\Core\Responses\Concerns\HasEncodingParameters;
use LaravelJsonApi\Core\Responses\Concerns\IsResponsable;
use LaravelJsonApi\Core\Responses\Internal\PaginatedResourceResponse;
use LaravelJsonApi\Core\Responses\Internal\ResourceCollectionResponse;
use LaravelJsonApi\Core\Responses\Internal\ResourceResponse;
use function is_null;

class DataResponse implements Responsable
{

    use HasEncodingParameters;
    use IsResponsable;

    /**
     * @var Page|object|iterable|null
     */
    private $value;

    /**
     * @var bool|null
     */
    private ?bool $created = null;

    /**
     * Fluent constructor.
     *
     * @param Page|object|iterable|null $value
     * @return DataResponse
     */
    public static function make($value): self
    {
        return new self($value);
    }

    /**
     * DataResponse constructor.
     *
     * @param Page|object|iterable|null $value
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Mark the resource as created.
     *
     * @return $this
     */
    public function didCreate(): self
    {
        $this->created = true;

        return $this;
    }

    /**
     * Mark the resource as not created.
     *
     * @return $this
     */
    public function didntCreate(): self
    {
        $this->created = false;

        return $this;
    }

    /**
     * @param Request $request
     * @return ResourceCollectionResponse|ResourceResponse
     */
    public function prepareResponse($request): Responsable
    {
        return $this
            ->prepareDataResponse($request)
            ->withServer($this->server)
            ->withJsonApi($this->jsonApi())
            ->withMeta($this->meta)
            ->withLinks($this->links)
            ->withEncodeOptions($this->encodeOptions)
            ->withIncludePaths($this->includePaths)
            ->withSparseFieldSets($this->fieldSets)
            ->withHeaders($this->headers());
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this
            ->prepareResponse($request)
            ->toResponse($request);
    }

    /**
     * Convert the data member to a response class.
     *
     * @param $request
     * @return PaginatedResourceResponse|ResourceCollectionResponse|ResourceResponse
     */
    private function prepareDataResponse($request)
    {
        if ($this->value instanceof Page) {
            return new PaginatedResourceResponse($this->value);
        }

        if (is_null($this->value)) {
            return new ResourceResponse(null);
        }

        if ($this->value instanceof JsonApiResource) {
            return $this->value
                ->prepareResponse($request)
                ->withCreated($this->created);
        }

        $resources = $this->server()->resources();

        if (is_object($this->value) && $resources->exists($this->value)) {
            return $resources
                ->create($this->value)
                ->prepareResponse($request)
                ->withCreated($this->created);
        }

        return (new ResourceCollection($this->value))->prepareResponse($request);
    }

}
