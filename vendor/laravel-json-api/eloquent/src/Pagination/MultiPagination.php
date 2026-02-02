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
use LaravelJsonApi\Eloquent\Contracts\Paginator;

class MultiPagination implements Paginator
{
    /**
     * @var Paginator[]
     */
    private array $paginators;

    /**
     * @var array|null
     */
    private ?array $keys = null;

    /**
     * MultiPagination constructor.
     *
     * @param Paginator ...$paginators
     */
    public function __construct(Paginator ...$paginators)
    {
        $this->paginators = $paginators;
    }

    /**
     * @inheritDoc
     */
    public function withColumns($columns): Paginator
    {
        foreach ($this->paginators as $paginator) {
            $paginator->withColumns($columns);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function keys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $keys = [];

        foreach ($this->paginators as $paginator) {
            $keys = [
                ...$keys,
                ...$paginator->keys(),
            ];
        }

        return $this->keys = array_values(array_unique($keys));
    }

    /**
     * @inheritDoc
     */
    public function withKeyName(string $column): Paginator
    {
        foreach ($this->paginators as $paginator) {
            $paginator->withKeyName($column);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function paginate($query, array $page): Page
    {
        $pageKeys = array_keys($page);
        $selected = null;

        foreach ($this->paginators as $paginator) {
            $keys = $paginator->keys();
            $intersection = array_intersect($keys, $pageKeys);

            /** Exact match for a paginator - immediately use this one. */
            if (!empty($intersection) && empty(array_diff($pageKeys, $keys))) {
                $selected = $paginator;
                break;
            }

            /**
             * Does match but has a diff, we'll remember the paginator
             * and use it if there are no exact matches.
             */
            if ($selected === null && !empty($intersection)) {
                $selected = $paginator;
            }
        }

        if ($selected !== null) {
            return $selected->paginate($query, $page);
        }

        throw new \LogicException(
            'Could not determine which paginator to use. ' .
            'Use validation to ensure the client provides query parameters that match at least one paginator. ' .
            'Keys received: ' . implode(',', $pageKeys),
        );
    }
}