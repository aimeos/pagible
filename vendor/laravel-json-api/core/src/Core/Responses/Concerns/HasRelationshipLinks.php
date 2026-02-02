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

trait HasRelationshipLinks
{

    /**
     * @var bool
     */
    private bool $hasRelationLinks = true;

    /**
     * Set whether relationship links should appear in the top-level links member.
     *
     * @param bool $bool
     * @return $this
     */
    public function withRelationshipLinks(bool $bool = true): self
    {
        $this->hasRelationLinks = $bool;

        return $this;
    }

    /**
     * Do not add relationship links to the top-level meta member.
     *
     * @return $this
     */
    public function withoutRelationshipLinks(): self
    {
        $this->hasRelationLinks = false;

        return $this;
    }

}
