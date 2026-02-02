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

use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Json\Json;

trait HasMeta
{

    /**
     * @var Hash|null
     */
    private ?Hash $meta = null;

    /**
     * Get the meta member.
     *
     * @return Hash
     */
    public function meta(): Hash
    {
        if ($this->meta) {
            return $this->meta;
        }

        return $this->meta = new Hash();
    }

    /**
     * Replace the meta member.
     *
     * @param mixed|null $meta
     * @return $this
     */
    public function setMeta($meta): self
    {
        $this->meta = Json::hash($meta);

        return $this;
    }

    /**
     * Remove meta.
     *
     * @return $this
     */
    public function withoutMeta(): self
    {
        $this->meta = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasMeta(): bool
    {
        if ($this->meta) {
            return $this->meta->isNotEmpty();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function doesntHaveMeta(): bool
    {
        return !$this->hasMeta();
    }
}
