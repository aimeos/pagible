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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelJsonApi\Contracts\Pagination\Page;
use LaravelJsonApi\Contracts\Schema\ID;
use LaravelJsonApi\Core\Pagination\Concerns\HasPageMeta;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\Pagination\Cursor\Cursor;
use LaravelJsonApi\Eloquent\Pagination\Cursor\CursorBuilder;
use LaravelJsonApi\Eloquent\Pagination\Cursor\CursorPage;

final class CursorPagination implements Paginator
{
    use HasPageMeta;

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
     * @var string
     */
    private string $direction;

    /**
     * @var string|null
     */
    private ?string $primaryKey = null;

    /**
     * @var string|array<string>|null
     */
    private string|array|null $columns = null;

    /**
     * @var int|null
     */
    private ?int $defaultPerPage = null;

    /**
     * @var bool
     */
    private bool $withTotal;

    /**
     * @var bool
     */
    private bool $withTotalOnFirstPage;

    /**
     * @var bool
     */
    private bool $keySort = true;

    /**
     * CursorPagination constructor.
     *
     * @param ID $id
     */
    public function __construct(private readonly ID $id)
    {
        $this->before = 'before';
        $this->after = 'after';
        $this->limit = 'limit';
        $this->metaKey = 'page';
        $this->direction = 'desc';
        $this->withTotal = false;
        $this->withTotalOnFirstPage = false;
    }

    /**
     * Fluent constructor.
     *
     * @param ID $id
     * @return self
     */
    public static function make(ID $id): self
    {
        return new self($id);
    }

    /**
     * Set the "after" key.
     *
     * @return $this
     */
    public function withAfterKey(string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->after = $key;

        return $this;
    }

    /**
     * Set the "before" key.
     *
     * @return $this
     */
    public function withBeforeKey(string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->before = $key;

        return $this;
    }

    /**
     * Set the "limit" key.
     *
     * @return $this
     */
    public function withLimitKey(string $key): self
    {
        if (empty($key)) {
            throw new \InvalidArgumentException('Expecting a non-empty string.');
        }

        $this->limit = $key;

        return $this;
    }

    /**
     * Use an ascending order.
     *
     * @return $this
     */
    public function withAscending(): self
    {
        $this->direction = 'asc';

        return $this;
    }

    /**
     * Use the provided number as the default items per-page.
     *
     * If null, the default per-page set on the model class will be used.
     *
     * @return $this
     */
    public function withDefaultPerPage(?int $perPage): self
    {
        $this->defaultPerPage = $perPage;

        return $this;
    }

    /**
     * @param bool $withTotal
     * @return $this
     */
    public function withTotal(bool $withTotal = true): self
    {
        $this->withTotal = $withTotal;

        return $this;
    }

    /**
     * @param bool $withTotal
     * @return $this
     */
    public function withTotalOnFirstPage(bool $withTotal = true): self
    {
        $this->withTotalOnFirstPage = $withTotal;

        return $this;
    }

    /**
     * @param string $column
     * @return $this
     */
    public function withKeyName(string $column): self
    {
        $this->primaryKey = $column;

        return $this;
    }

    /**
     * @param string|array<string> $columns
     * @return $this
     */
    public function withColumns($columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param bool $keySort
     * @return $this
     */
    public function withKeySort(bool $keySort = true): self
    {
        $this->keySort = $keySort;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutKeySort(): self
    {
        return $this->withKeySort(false);
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return [
            $this->before,
            $this->after,
            $this->limit,
        ];
    }

    /**
     * @param Builder|Relation $query
     * @param array<string, mixed> $page
     */
    public function paginate($query, array $page): Page
    {
        $cursor = $this->cursor($page);

        $withTotal = $this->withTotal
            || ($this->withTotalOnFirstPage
            && !$cursor->isBefore()
            && !$cursor->isAfter());

        $paginator = $this
            ->query($query)
            ->withDirection($this->direction)
            ->withKeySort($this->keySort)
            ->withDefaultPerPage($this->defaultPerPage)
            ->withTotal($withTotal)
            ->paginate($cursor, $this->columns ?? ['*']);

        return CursorPage::make($paginator)
            ->withBeforeParam($this->before)
            ->withAfterParam($this->after)
            ->withLimitParam($this->limit)
            ->withMeta($this->hasMeta)
            ->withNestedMeta($this->metaKey)
            ->withMetaCase($this->metaCase);
    }

    /**
     * Create a new cursor query.
     */
    private function query(Builder|Relation $query): CursorBuilder
    {
        return new CursorBuilder($query, $this->id, $this->primaryKey);
    }

    /**
     * Extract the cursor from the provided paging parameters.
     *
     * @param array<string, mixed> $page
     */
    private function cursor(array $page): Cursor
    {
        $before = $page[$this->before] ?? null;
        $after = $page[$this->after] ?? null;
        $limit = $page[$this->limit] ?? null;

        return new Cursor(
            !is_null($before) ? strval($before) : null,
            !is_null($after) ? strval($after) : null,
            !is_null($limit) ? intval($limit) : null,
        );
    }
}
