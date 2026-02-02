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

use InvalidArgumentException;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Pagination\AbstractPage;

class CursorPage extends AbstractPage
{
    /**
     * @var string
     */
    private string $before;

    /**
     * @var string
     */
    private string $after;

    /**
     * @var string
     */
    private string $limit;

    /**
     * CursorPage constructor.
     *
     * @param CursorPaginator $paginator
     */
    public function __construct(private readonly CursorPaginator $paginator)
    {
    }

    /**
     * Fluent constructor.
     *
     * @param CursorPaginator $paginator
     * @return self
     */
    public static function make(CursorPaginator $paginator): self
    {
        return new self($paginator);
    }

    /**
     * Set the "after" parameter.
     *
     * @return $this
     */
    public function withAfterParam(string $key): self
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->after = $key;

        return $this;
    }

    /**
     * Set the "before" parameter.
     *
     * @return $this
     */
    public function withBeforeParam(string $key): self
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->before = $key;

        return $this;
    }

    /**
     * Set the "limit" parameter.
     *
     * @return $this
     */
    public function withLimitParam(string $key): self
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->limit = $key;

        return $this;
    }

    /**
     * @return Link|null
     */
    public function first(): ?Link
    {
        return new Link('first', $this->url([
            $this->limit => $this->paginator->getPerPage(),
        ]));
    }

    /**
     * @return Link|null
     */
    public function prev(): ?Link
    {
        if ($this->paginator->isNotEmpty() && $this->paginator->hasPrev()) {
            return new Link('prev', $this->url([
                $this->before => $this->paginator->firstItem(),
                $this->limit => $this->paginator->getPerPage(),
            ]));
        }

        return null;
    }

    /**
     * @return Link|null
     */
    public function next(): ?Link
    {
        if ($this->paginator->isNotEmpty() && $this->paginator->hasNext()) {
            return new Link('next', $this->url([
                $this->after => $this->paginator->lastItem(),
                $this->limit => $this->paginator->getPerPage(),
            ]));
        }

        return null;
    }

    /**
     * @return Link|null
     */
    public function last(): ?Link
    {
        return null;
    }

    /**
     * @param array<string,mixed> $page
     */
    public function url(array $page): string
    {
        return $this->paginator->path() . '?' . $this->stringifyQuery($page);
    }

    /**
     * @return \Traversable
     */
    public function getIterator(): \Traversable
    {
        yield from $this->paginator;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->paginator->count();
    }

    /**
     * @return array<string, mixed>
     */
    protected function metaForPage(): array
    {
        $meta = [
            'perPage' => $this->paginator->getPerPage(),
            'from' => $this->paginator->getFrom(),
            'to' => $this->paginator->getTo(),
            'hasMore' => $this->paginator->hasMorePages(),
        ];
        $total = $this->paginator->getTotal();
        if ($total !== null) {
            $meta['total'] = $total;
        }

        return $meta;
    }
}
