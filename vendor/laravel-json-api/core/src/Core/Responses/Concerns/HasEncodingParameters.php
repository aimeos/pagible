<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Responses\Concerns;

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Query\QueryParameters;
use LaravelJsonApi\Core\Query\FieldSets;
use LaravelJsonApi\Core\Query\IncludePaths;

trait HasEncodingParameters
{

    /**
     * @var IncludePaths|null
     */
    private ?IncludePaths $includePaths = null;

    /**
     * @var FieldSets|null
     */
    private ?FieldSets $fieldSets = null;

    /**
     * Set the response JSON:API query parameters.
     *
     * @param QueryParameters $parameters
     * @return $this
     */
    public function withQueryParameters(QueryParameters $parameters): self
    {
        return $this
            ->withIncludePaths($parameters->includePaths())
            ->withSparseFieldSets($parameters->sparseFieldSets());
    }

    /**
     * Set the response include paths.
     *
     * If no include paths are set, the response will determine the include paths
     * from the HTTP request.
     *
     * @param IncludePaths|array|string|null $includePaths
     * @return $this
     */
    public function withIncludePaths($includePaths): self
    {
        $this->includePaths = IncludePaths::nullable($includePaths);

        return $this;
    }

    /**
     * Set the response sparse field sets.
     *
     * If no field sets are set, the response will determine the sparse field sets
     * from the HTTP request.
     *
     * @param FieldSets|array|null $fieldSets
     * @return $this
     */
    public function withSparseFieldSets($fieldSets): self
    {
        $this->fieldSets = FieldSets::nullable($fieldSets);

        return $this;
    }

    /**
     * Get the include paths to use when encoding the response.
     *
     * @param Request $request
     * @return IncludePaths|null
     */
    protected function includePaths($request): ?IncludePaths
    {
        if ($this->includePaths) {
            return $this->includePaths;
        }

        return $this->extractIncludePaths($request);
    }

    /**
     * Extract include paths from the provided request.
     *
     * @param Request $request
     * @return IncludePaths|null
     */
    protected function extractIncludePaths($request): ?IncludePaths
    {
        if ($request instanceof QueryParameters) {
            return $request->includePaths();
        }

        if ($request->query->has('include')) {
            return IncludePaths::fromString($request->query('include') ?: '');
        }

        return null;
    }

    /**
     * Get the sparse field sets to use when encoding the response.
     *
     * @param Request $request
     * @return FieldSets|null
     */
    protected function sparseFieldSets($request): ?FieldSets
    {
        if ($this->fieldSets) {
            return $this->fieldSets;
        }

        return $this->extractSparseFieldSets($request);
    }

    /**
     * Extract sparse field sets from the provided request.
     *
     * @param Request $request
     * @return FieldSets|null
     */
    protected function extractSparseFieldSets($request): ?FieldSets
    {
        if ($request instanceof QueryParameters) {
            return $request->sparseFieldSets();
        }

        if ($request->query->has('fields')) {
            return FieldSets::fromArray($request->query('fields') ?: []);
        }

        return null;
    }
}
