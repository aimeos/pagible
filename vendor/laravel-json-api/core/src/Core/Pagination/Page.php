<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Pagination\Page as PageContract;
use LaravelJsonApi\Core\Document\Link;
use Traversable;
use function collect;
use function count;
use function is_null;

class Page extends AbstractPage
{

    /**
     * @var Paginator|LengthAwarePaginator
     */
    private Paginator $paginator;

    /**
     * @var string
     */
    private string $pageParam = 'number';

    /**
     * @var string
     */
    private string $perPageParam = 'size';

    /**
     * @param PageContract|Paginator $page
     * @return PageContract
     */
    public static function cast($page): PageContract
    {
        if ($page instanceof PageContract) {
            return $page;
        }

        if ($page instanceof Paginator) {
            return new self($page);
        }

        throw new InvalidArgumentException('Expecting a JSON:API page or a Laravel paginator.');
    }

    /**
     * Fluent constructor.
     *
     * @param Paginator $paginator
     * @return Page
     */
    public static function make(Paginator $paginator): self
    {
        return new self($paginator);
    }

    /**
     * Page constructor.
     *
     * @param Paginator $paginator
     */
    public function __construct(Paginator $paginator)
    {
        $this->paginator = $paginator;
    }

    /**
     * @inheritDoc
     */
    public function first(): ?Link
    {
        return new Link('first', $this->url(1));
    }

    /**
     * @inheritDoc
     */
    public function prev(): ?Link
    {
        if (1 < $this->paginator->currentPage()) {
            return new Link('prev', $this->url(
                $this->paginator->currentPage() - 1
            ));
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function next(): ?Link
    {
        if ($this->paginator->hasMorePages()) {
            return new Link('next', $this->url(
                $this->paginator->currentPage() + 1
            ));
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function last(): ?Link
    {
        if ($this->isLengthAware()) {
            return new Link('last', $this->url($this->paginator->lastPage()));
        }

        return null;
    }

    /**
     * @param int $page
     * @return string
     */
    public function url(int $page): string
    {
        $params = $this->stringifyQuery([
            $this->pageParam => $page,
            $this->perPageParam => $this->paginator->perPage(),
        ]);

        return $this->paginator->path() . '?' . $params;
    }

    /**
     * Set the key for the page number parameter.
     *
     * @param string $key
     * @return $this
     */
    public function withPageParam(string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Page parameter cannot be an empty string.');
        }

        $this->pageParam = $key;

        return $this;
    }

    /**
     * Set the key for the per-page parameter.
     *
     * @param string $key
     * @return $this
     */
    public function withPerPageParam(string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Per-page parameter cannot be an empty string.');
        }

        $this->perPageParam = $key;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->paginator;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->paginator);
    }

    /**
     * @inheritDoc
     */
    protected function metaForPage(): array
    {
        return collect([
            'currentPage' => (int) $this->paginator->currentPage(),
            'from' => (int) $this->paginator->firstItem(),
            'lastPage' => $this->isLengthAware() ? (int) $this->paginator->lastPage() : null,
            'perPage' => (int) $this->paginator->perPage(),
            'to' => (int) $this->paginator->lastItem(),
            'total' => $this->isLengthAware() ? (int) $this->paginator->total() : null,
        ])->reject(static fn ($value) => is_null($value))->all();
    }

    /**
     * @return bool
     */
    private function isLengthAware(): bool
    {
        return $this->paginator instanceof LengthAwarePaginator;
    }

}
