<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Contracts\Encoder;

use LaravelJsonApi\Contracts\Serializable;
use LaravelJsonApi\Core\Document\JsonApi;

interface JsonApiDocument extends Serializable
{

    /**
     * Set the top-level JSON:API member.
     *
     * Encoders MUST only use the provided JSON:API object
     * if it is non-empty. Otherwise encoders should fall back
     * to the default JSON:API object, which is provided by the
     * server that is doing the encoding.
     *
     * @param JsonApi|array|string|null $jsonApi
     * @return $this
     */
    public function withJsonApi($jsonApi): self;

    /**
     * Forget the top-level JSON:API member.
     *
     * @return $this
     */
    public function withoutJsonApi(): self;

    /**
     * Set the top-level links member.
     *
     * @param $links
     * @return $this
     */
    public function withLinks($links): self;

    /**
     * Forget top-level links.
     *
     * @return $this
     */
    public function withoutLinks(): self;

    /**
     * Set the top-level meta member.
     *
     * @param $meta
     * @return $this
     */
    public function withMeta($meta): self;

    /**
     * Forget top-level meta.
     *
     * @return $this
     */
    public function withoutMeta(): self;

}
