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
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Pagination\Page as PageContract;
use LaravelJsonApi\Core\Pagination\Concerns\HasPageMeta;
use LaravelJsonApi\Core\Pagination\Concerns\HasPageNumbers;
use LaravelJsonApi\Core\Pagination\Page;
use LaravelJsonApi\Eloquent\Contracts\Paginator;

class PagePagination implements Paginator
{

    use HasPageMeta;
    use HasPageNumbers;

    /**
     * @var array|null
     */
    private ?array $columns = null;

    /**
     * @var bool|null
     */
    private ?bool $simplePagination = null;

    /**
     * @var string|null
     */
    private ?string $primaryKey = null;

    /**
     * Fluent constructor.
     *
     * @return PagePagination
     */
    public static function make(): self
    {
        return new static();
    }

    /**
     * Override the page resolver to read the page parameter from the JSON:API request.
     *
     * @return void
     */
    public static function bindPageResolver(): void
    {
        AbstractPaginator::currentPageResolver(static function ($pageName) {
            $pagination = \request()->query($pageName);
            return $pagination['number'] ?? null;
        });
    }

    /**
     * @inheritDoc
     */
    public function withKeyName(string $column): self
    {
        $this->primaryKey = $column;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withColumns($columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Use simple pagination.
     *
     * @return $this
     */
    public function withSimplePagination(): self
    {
        $this->simplePagination = true;

        return $this;
    }

    /**
     * Use length-aware pagination.
     *
     * @return $this
     */
    public function withLengthAwarePagination(): self
    {
        $this->simplePagination = false;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function paginate($query, array $page): PageContract
    {
        $paginator = $this
            ->defaultOrder($query)
            ->query($query, $page);

        return Page::make($paginator)
            ->withPageParam($this->pageKey)
            ->withPerPageParam($this->perPageKey)
            ->withMeta($this->hasMeta)
            ->withNestedMeta($this->metaKey)
            ->withMetaCase($this->metaCase);
    }

    /**
     * Get the number of items to return per-page.
     *
     * If this method returns zero, the default per-page will be used.
     *
     * @param array $page
     * @return int
     */
    protected function getPerPage(array $page): int
    {
        $perPage = $page[$this->perPageKey] ?? 0;

        return (int) $perPage;
    }

    /**
     * Get the default per-page value for the query.
     *
     * If the query is an Eloquent builder, we can pass in `null` as the default,
     * which then delegates to the model to get the default. Otherwise the Laravel
     * standard default is 15.
     *
     * @return int|null
     */
    protected function getDefaultPerPage(): ?int
    {
        return $this->defaultPerPage;
    }

    /**
     * @return array
     */
    protected function getColumns(): array
    {
        return $this->columns ?: ['*'];
    }

    /**
     * @return bool
     */
    protected function isSimplePagination(): bool
    {
        return (bool) $this->simplePagination;
    }

    /**
     * @param Builder|Relation $query
     * @return bool
     */
    private function willSimplePaginate($query): bool
    {
        return $this->isSimplePagination() && method_exists($query, 'simplePaginate');
    }

    /**
     * Apply a deterministic order to the page.
     *
     * @param Builder|Relation $query
     * @return $this
     * @see https://github.com/cloudcreativity/laravel-json-api/issues/313
     */
    private function defaultOrder($query): self
    {
        if ($this->doesRequireOrdering($query)) {
            $query->orderBy(
                $query->getModel()->qualifyColumn($this->primaryKey)
            );
        }

        return $this;
    }

    /**
     * Do we need to apply a deterministic order to the query?
     *
     * If the primary key has not been used for a sort order already, we use it
     * to ensure the page has a deterministic order.
     *
     * When checking for the primary key in the sort orders, we also need to check
     * the qualified version of the column name - as it may have been ordered using
     * the qualified name.
     *
     * @param Builder|Relation $query
     * @return bool
     */
    private function doesRequireOrdering($query): bool
    {
        if (!$this->primaryKey) {
            return false;
        }

        $qualified = $query->getModel()->qualifyColumn($this->primaryKey);
        $query = $query->toBase();

        return !Collection::make($query->orders ?: [])->contains(function (array $order) use ($qualified) {
            $col = $order['column'] ?? '';
            return ($this->primaryKey === $col || $qualified === $col);
        });
    }

    /**
     * @param Builder|Relation $query
     * @param array $page
     * @return mixed
     */
    private function query($query, array $page)
    {
        $size = $this->getPerPage($page) ?: $this->getDefaultPerPage();
        $cols = $this->getColumns();
        $pageNumber = $page[$this->pageKey] ?? null;

        return $this->willSimplePaginate($query) ?
            $query->simplePaginate($size, $cols, $this->pageKey, $pageNumber) :
            $query->paginate($size, $cols, $this->pageKey, $pageNumber);
    }
}
