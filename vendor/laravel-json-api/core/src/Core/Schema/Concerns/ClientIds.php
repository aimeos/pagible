<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

trait ClientIds
{

    /**
     * @var bool
     */
    private bool $clientIds = false;

    /**
     * Mark the ID as accepting client-generated ids.
     *
     * @param bool $bool
     * @return $this
     */
    public function clientIds(bool $bool = true): self
    {
        $this->clientIds = $bool;

        return $this;
    }

    /**
     * Does the resource accept client generated ids?
     *
     * @return bool
     */
    public function acceptsClientIds(): bool
    {
        return $this->clientIds;
    }
}
