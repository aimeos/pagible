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

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Pagination\Page as PageContract;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Core\Query\QueryParameters;
use LaravelJsonApi\Core\Responses\Internal\PaginatedResourceResponse;
use function ksort;

abstract class AbstractPage implements PageContract
{

    /**
     * The key-case of the page meta.
     *
     * @var string|null
     */
    private ?string $metaCase = null;

    /**
     * The key to nest page meta under.
     *
     * @var string|null
     */
    private ?string $metaKey = null;

    /**
     * Whether the page has meta.
     *
     * @var bool
     */
    private bool $hasMeta = true;

    /**
     * The query parameters that must be used for links.
     *
     * @var QueryParameters|null
     */
    private ?QueryParameters $queryParameters = null;

    /**
     * Get the link to the first page.
     *
     * @return Link|null
     */
    abstract public function first(): ?Link;

    /**
     * Get the link to the previous page.
     *
     * @return Link|null
     */
    abstract public function prev(): ?Link;

    /**
     * Get the link to the next page.
     *
     * @return Link|null
     */
    abstract public function next(): ?Link;

    /**
     * Get the link to the last page.
     *
     * @return Link|null
     */
    abstract public function last(): ?Link;

    /**
     * Get the page's meta.
     *
     * @return array
     */
    abstract protected function metaForPage(): array;

    /**
     * Use snake-case keys in the meta object.
     *
     * @return $this
     */
    public function withSnakeCaseMeta(): self
    {
        return $this->withMetaCase('snake');
    }

    /**
     * Use dash-case keys in the meta object.
     *
     * @return $this
     */
    public function withDashCaseMeta(): self
    {
        return $this->withMetaCase('dash');
    }

    /**
     * Use camel-case keys in the meta object.
     *
     * @return $this
     */
    public function withCamelCaseMeta(): self
    {
        return $this->withMetaCase('camel');
    }

    /**
     * Set the key-case to use for meta.
     *
     * @param string|null $case
     * @return $this
     */
    public function withMetaCase(?string $case): self
    {
        if (in_array($case, [null, 'snake', 'dash'], true)) {
            $this->metaCase = $case;
            return $this;
        }

        throw new \InvalidArgumentException('Invalid meta case: ' . $case ?? 'null');
    }

    /**
     * Nest page meta using the provided key.
     *
     * @param string|null $key
     * @return $this
     */
    public function withNestedMeta(?string $key = 'page'): self
    {
        $this->metaKey = $key;

        return $this;
    }

    /**
     * Do not show page meta.
     *
     * @return $this
     */
    public function withoutMeta(): self
    {
        return $this->withMeta(false);
    }

    /**
     * Set whether to include page meta using a boolean flag.
     *
     * @param bool $bool
     * @return $this
     */
    public function withMeta(bool $bool): self
    {
        $this->hasMeta = $bool;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withQuery($query): PageContract
    {
        $this->queryParameters = QueryParameters::cast($query);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function meta(): array
    {
        if (false === $this->hasMeta) {
            return [];
        }

        $hash = Hash::cast($this->metaForPage())->sortKeys();

        if ($this->metaCase) {
            $hash->useCase($this->metaCase);
        }

        if ($this->metaKey) {
            return [$this->metaKey => $hash->all()];
        }

        return $hash->all();
    }

    /**
     * @inheritDoc
     */
    public function links(): Links
    {
        return new Links(...array_filter([
            $this->first(),
            $this->prev(),
            $this->next(),
            $this->last(),
        ]));
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return PaginatedResourceResponse
     */
    public function prepareResponse($request): PaginatedResourceResponse
    {
        return new PaginatedResourceResponse($this);
    }

    /**
     * @inheritDoc
     */
    public function toResponse($request)
    {
        return $this->prepareResponse($request)->toResponse($request);
    }

    /**
     * Build new query parameters for the supplied page.
     *
     * @param array $page
     * @return array
     */
    protected function buildQuery(array $page): array
    {
        ksort($page);

        return Collection::make($this->queryParameters ? $this->queryParameters->toQuery() : [])
            ->put('page', $page)
            ->sortKeys()
            ->all();
    }

    /**
     * Get string query parameters for the supplied page.
     *
     * @param array $page
     * @return string
     */
    protected function stringifyQuery(array $page): string
    {
        return Arr::query($this->buildQuery($page));
    }
}
