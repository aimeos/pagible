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

trait HasRelationshipMeta
{

    /**
     * @var bool
     */
    private bool $hasRelationMeta = true;

    /**
     * Set whether relationship meta should appear in the top-level meta member.
     *
     * @param bool $bool
     * @return $this
     */
    public function withRelationshipMeta(bool $bool = true): self
    {
        $this->hasRelationMeta = $bool;

        return $this;
    }

    /**
     * Do not add relationship meta to the top-level meta member.
     *
     * @return $this
     */
    public function withoutRelationshipMeta(): self
    {
        $this->hasRelationMeta = false;

        return $this;
    }

}
