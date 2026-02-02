<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Parser;

use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Json\Json;
use Neomerx\JsonApi\Parser\IdentifierAndResource as BaseIdentifierAndResource;

class IdentifierAndResource extends BaseIdentifierAndResource
{

    /**
     * Cached meta.
     *
     * @var Hash|null
     */
    private ?Hash $meta = null;

    /**
     * Cached identifier meta.
     *
     * @var Hash|null
     */
    private ?Hash $identifierMeta = null;

    /**
     * @inheritDoc
     */
    public function hasIdentifierMeta(): bool
    {
        $this->cacheIdentifierMeta();

        return $this->identifierMeta->isNotEmpty();
    }

    /**
     * @inheritDoc
     */
    public function getIdentifierMeta()
    {
        $this->cacheIdentifierMeta();

        return $this->identifierMeta->jsonSerialize();
    }

    /**
     * @inheritDoc
     */
    public function hasResourceMeta(): bool
    {
        $this->cacheMeta();

        return $this->meta->isNotEmpty();
    }

    /**
     * @inheritDoc
     */
    public function getResourceMeta()
    {
        $this->cacheMeta();

        return $this->meta->jsonSerialize();
    }

    /**
     * Cache resource meta.
     *
     * @return void
     */
    private function cacheMeta(): void
    {
        if (null === $this->meta) {
            $this->meta = Json::hash(parent::getResourceMeta());
        }
    }

    /**
     * Cache resource identifier meta.
     *
     * @return void
     */
    private function cacheIdentifierMeta(): void
    {
        if (null === $this->identifierMeta) {
            $this->identifierMeta = Json::hash(parent::getIdentifierMeta());
        }
    }

}
