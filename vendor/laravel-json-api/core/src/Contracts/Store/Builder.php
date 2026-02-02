<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Store;

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Query\QueryParameters;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\RelationshipPath;

interface Builder
{

    /**
     * Use the request as the context for the building action.
     *
     * When setting the request, the request query parameters MUST be used.
     *
     * @param Request $request
     * @return $this
     */
    public function withRequest(Request $request): self;

    /**
     * Use the provided query parameters for the building action.
     *
     * Query parameters can be specified to either override the query parameters
     * from the request context, or to execute the building action outside of a
     * HTTP request.
     *
     * @param QueryParameters $query
     * @return $this
     */
    public function withQuery(QueryParameters $query): self;

    /**
     * Eager load resources using the provided JSON:API include paths.
     *
     * Manually sets the eager load paths for the building action. This can
     * be used to override the query parameters already set, or to specify the
     * include paths when manually calling building actions.
     *
     * @param IncludePaths|RelationshipPath|array|string|null $includePaths
     * @return $this
     */
    public function with($includePaths): self;
}
