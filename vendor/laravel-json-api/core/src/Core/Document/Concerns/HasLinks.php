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

use LaravelJsonApi\Core\Document\Links;

trait HasLinks
{

    /**
     * @var Links|null
     */
    private ?Links $links = null;

    /**
     * Get the links member.
     *
     * @return Links
     */
    public function links(): Links
    {
        if ($this->links) {
            return $this->links;
        }

        return $this->links = new Links();
    }

    /**
     * Replace the links member.
     *
     * @param mixed|null $links
     * @return $this
     */
    public function setLinks($links): self
    {
        $this->links = Links::cast($links);

        return $this;
    }

    /**
     * Remove links.
     *
     * @return $this
     */
    public function withoutLinks(): self
    {
        $this->links = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasLinks(): bool
    {
        if ($this->links) {
            return $this->links->isNotEmpty();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function doesntHaveLinks(): bool
    {
        return !$this->hasLinks();
    }
}
