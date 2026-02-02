<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Pagination;

use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Eloquent\Contracts\Proxy;
use Traversable;

final class ProxyPage implements Page
{

    /**
     * @var Page
     */
    private Page $page;

    /**
     * @var Proxy
     */
    private Proxy $proxy;

    /**
     * ProxyPage constructor.
     *
     * @param Page $page
     * @param Proxy $proxy
     */
    public function __construct(Page $page, Proxy $proxy)
    {
        $this->page = $page;
        $this->proxy = $proxy;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->proxy->iterator($this->page);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return $this->page->count();
    }

    /**
     * @inheritDoc
     */
    public function meta(): array
    {
        return $this->page->meta();
    }

    /**
     * @inheritDoc
     */
    public function links(): Links
    {
        return $this->page->links();
    }

    /**
     * @inheritDoc
     */
    public function withQuery($query): Page
    {
        $this->page->withQuery($query);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this->page->toResponse($request);
    }

}
