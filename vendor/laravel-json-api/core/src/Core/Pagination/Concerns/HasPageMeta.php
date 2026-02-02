<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Pagination\Concerns;

trait HasPageMeta
{

    /**
     * @var string|null
     */
    private ?string $metaKey = 'page';

    /**
     * @var string|null
     */
    private ?string $metaCase = null;

    /**
     * @var bool
     */
    private bool $hasMeta = true;

    /**
     * Set the key for the paging meta.
     *
     * Use this to 'nest' the paging meta in a sub-key of the JSON API document's top-level meta object.
     * A string sets the key to use for nesting. Use `null` to indicate no nesting.
     *
     * @param string|null $key
     * @return $this
     */
    public function withMetaKey(?string $key): self
    {
        $this->metaKey = $key ?: null;

        return $this;
    }

    /**
     * Mark the paginator as not nesting page meta.
     *
     * @return $this
     */
    public function withoutNestedMeta(): self
    {
        return $this->withMetaKey(null);
    }

    /**
     * Use snake-case meta keys.
     *
     * @return $this
     */
    public function withSnakeCaseMeta(): self
    {
        $this->metaCase = 'snake';

        return $this;
    }

    /**
     * Use dash-case meta keys.
     *
     * @return $this
     */
    public function withDashCaseMeta(): self
    {
        $this->metaCase = 'dash';

        return $this;
    }

    /**
     * Use camel-case meta keys.
     *
     * @return $this
     */
    public function withCamelCaseMeta(): self
    {
        $this->metaCase = null;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutMeta(): self
    {
        $this->hasMeta = false;

        return $this;
    }
}
