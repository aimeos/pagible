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

use Illuminate\Http\Request;
use LaravelJsonApi\Contracts\Query\QueryParameters as QueryParametersContract;
use LaravelJsonApi\Core\Query\Custom\ExtendedQueryParameters;

trait HasQueryParameters
{

    /**
     * @var Request|null
     */
    private ?Request $request = null;

    /**
     * @var ExtendedQueryParameters
     */
    private ExtendedQueryParameters $queryParameters;

    /**
     * @inheritDoc
     */
    public function withRequest(Request $request): self
    {
        $this->request = $request;
        $this->queryParameters = ExtendedQueryParameters::cast($request);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withQuery(QueryParametersContract $query): self
    {
        $this->queryParameters = ExtendedQueryParameters::cast($query);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function with($includePaths): self
    {
        $this->queryParameters->setIncludePaths($includePaths);

        return $this;
    }

    /**
     * @param mixed $countable
     * @return $this
     */
    public function withCount($countable): self
    {
        $this->queryParameters->setCountable($countable);

        return $this;
    }
}
