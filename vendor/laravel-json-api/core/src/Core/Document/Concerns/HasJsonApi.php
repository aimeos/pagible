<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Document\Concerns;

use LaravelJsonApi\Core\Document\JsonApi;

trait HasJsonApi
{

    /**
     * @var JsonApi|null
     */
    private ?JsonApi $jsonApi = null;

    /**
     * @return JsonApi
     */
    public function jsonApi(): JsonApi
    {
        if ($this->jsonApi) {
            return $this->jsonApi;
        }

        return $this->jsonApi = new JsonApi();
    }

    /**
     * @param mixed $jsonApi
     * @return $this
     */
    public function setJsonApi($jsonApi): self
    {
        $this->jsonApi = JsonApi::cast($jsonApi);

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutJsonApi(): self
    {
        $this->jsonApi = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasJsonApi(): bool
    {
        if ($this->jsonApi) {
            return $this->jsonApi->isNotEmpty();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function doesntHaveJsonApi(): bool
    {
        return !$this->hasJsonApi();
    }
}
