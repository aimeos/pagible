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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use LaravelJsonApi\Contracts\Schema\ID;
use LaravelJsonApi\Core\Schema\IdParser;

final class CursorBuilder
{
    /**
     * @var string
     */
    private readonly string $keyName;

    /**
     * @var string
     */
    private string $direction;

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
    private bool $keySort = true;

    /**
     * @var CursorParser
     */
    private readonly CursorParser $parser;

    /**
     * CursorBuilder constructor.
     *
     * @param Builder|Relation $query the column to use for the cursor
     * @param ID $id
     * @param string|null $key the key column that the before/after cursors related to
     */
    public function __construct(
        private readonly Builder|Relation $query,
        private readonly ID $id,
        ?string $key = null
    ) {
        $this->keyName = $key ?: $this->id->key();
        $this->parser = new CursorParser(IdParser::make($this->id), $this->keyName);
    }

    /**
     * Set the default number of items per-page.
     *
     * If null, the default from the `Model::getPage()` method will be used.
     *
     * @return $this
     */
    public function withDefaultPerPage(?int $perPage): self
    {
        $this->defaultPerPage = $perPage;

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
     * Set the query direction.
     *
     * @return $this
     */
    public function withDirection(string $direction): self
    {
        if (\in_array($direction, ['asc', 'desc'])) {
            $this->direction = $direction;

            return $this;
        }

        throw new \InvalidArgumentException('Unexpected query direction.');
    }

    /**
     * @param bool $withTotal
     * @return $this
     */
    public function withTotal(bool $withTotal): self
    {
        $this->withTotal = $withTotal;

        return $this;
    }

    /**
     * @param array<string> $columns
     */
    public function paginate(Cursor $cursor, array $columns = ['*']): CursorPaginator
    {
        $cursor = $cursor->withDefaultLimit($this->getDefaultPerPage());

        $this->applyKeySort();

        $total = $this->getTotal();
        $laravelPaginator = $this->query->cursorPaginate(
            $cursor->getLimit(),
            $columns,
            'cursor',
            $this->parser->decode($cursor),
        );
        $paginator = new CursorPaginator($this->parser, $laravelPaginator, $cursor, $total);

        return $paginator->withCurrentPath();
    }

    /**
     * @return void
     */
    private function applyKeySort(): void
    {
        if (!$this->keySort) {
            return;
        }

        if (
            empty($this->query->getQuery()->orders)
            || collect($this->query->getQuery()->orders)
                ->whereIn('column', [$this->keyName, $this->query->qualifyColumn($this->keyName)])
                ->isEmpty()
        ) {
            $this->query->orderBy($this->keyName, $this->direction);
        }
    }

    /**
     * @return int|null
     */
    private function getTotal(): ?int
    {
        return $this->withTotal ? $this->query->count() : null;
    }

    /**
     * @return int
     */
    private function getDefaultPerPage(): int
    {
        if (is_int($this->defaultPerPage)) {
            return $this->defaultPerPage;
        }

        return $this->query->getModel()->getPerPage();
    }
}
