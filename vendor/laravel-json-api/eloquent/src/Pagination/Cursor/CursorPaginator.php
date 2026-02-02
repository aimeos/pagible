<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Pagination\Cursor;

use Countable;
use Illuminate\Contracts\Pagination\CursorPaginator as LaravelCursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use IteratorAggregate;

final class CursorPaginator implements IteratorAggregate, Countable
{
    /**
     * @var Collection
     */
    private readonly Collection $items;

    /**
     * @var string|null
     */
    private ?string $path;

    /**
     * CursorPaginator constructor.
     *
     * @param CursorParser $parser
     * @param LaravelCursorPaginator $laravelPaginator
     * @param Cursor $cursor
     * @param int|null $total
     */
    public function __construct(
        private readonly CursorParser $parser,
        private readonly LaravelCursorPaginator $laravelPaginator,
        private readonly Cursor $cursor,
        private readonly ?int $total = null
    ) {
        $this->items = Collection::make($this->laravelPaginator->items());
    }

    /**
     * @return Collection
     */
    public function getItems(): Collection
    {
        return clone $this->items;
    }

    /**
     * @return string|null
     */
    public function firstItem(): ?string
    {

        if ($this->laravelPaginator->isEmpty()) {
            return null;
        }

        return $this->parser->encode($this->laravelPaginator->getCursorForItem($this->items->first(), false));
    }

    /**
     * @return string|null
     */
    public function lastItem(): ?string
    {
        if ($this->laravelPaginator->isEmpty()) {
            return null;
        }

        return $this->parser->encode($this->laravelPaginator->getCursorForItem($this->items->last()));
    }

    /**
     * @return bool
     */
    public function hasMorePages(): bool
    {
        return ($this->cursor->isBefore() && !$this->laravelPaginator->onFirstPage()) || $this->laravelPaginator->hasMorePages();
    }

    /**
     * @return bool
     */
    public function hasNext(): bool
    {
        return ((!$this->cursor->isAfter() && !$this->cursor->isBefore()) || $this->cursor->isAfter()) && $this->hasMorePages();
    }

    /**
     * @return bool
     */
    public function hasPrev(): bool
    {
        return ($this->cursor->isBefore() && $this->hasMorePages()) || $this->cursor->isAfter();
    }

    /**
     * @return bool
     */
    public function hasNoMorePages(): bool
    {
        return !$this->hasMorePages();
    }

    /**
     * @return int
     */
    public function getPerPage(): int
    {
        return $this->laravelPaginator->perPage();
    }

    /**
     * @return string|null
     */
    public function getFrom(): ?string
    {
        return $this->firstItem();
    }

    /**
     * @return string|null
     */
    public function getTo(): ?string
    {
        return $this->lastItem();
    }

    /**
     * @return int|null
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return $this
     */
    public function withCurrentPath(): self
    {
        $this->path = Paginator::resolveCurrentPath();

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function withPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get the base path for paginator generated URLs.
     */
    public function path(): ?string
    {
        return $this->path;
    }
}
