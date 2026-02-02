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

use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\LinkHref;
use LaravelJsonApi\Core\Document\Links;
use function sprintf;

/**
 * Trait HasRelationLinks
 *
 * @TODO not sure this is in use?
 */
trait HasRelationLinks
{

    /**
     * @var string
     */
    protected string $fieldName;

    /**
     * @var string
     */
    protected string $baseUri;

    /**
     * @var Links|null
     */
    private ?Links $links = null;

    /**
     * @return Links
     */
    public function links(): Links
    {
        if ($this->links) {
            return $this->links;
        }

        return $this->links = new Links(
            $this->selfLink(),
            $this->relatedLink()
        );
    }

    /**
     * @return bool
     */
    public function hasLinks(): bool
    {
        return $this->links()->isNotEmpty();
    }

    /**
     * @return string
     */
    protected function selfUrl(): string
    {
        return sprintf('%s/relationships/%s', $this->baseUri, $this->fieldName);
    }

    /**
     * @return Link
     */
    protected function selfLink(): Link
    {
        return new Link('self', new LinkHref($this->selfUrl()));
    }

    /**
     * @return string
     */
    protected function relatedUrl(): string
    {
        return sprintf('%s/%s', $this->baseUri, $this->fieldName);
    }

    /**
     * @return Link
     */
    protected function relatedLink(): Link
    {
        return new Link('related', new LinkHref($this->relatedUrl()));
    }
}
