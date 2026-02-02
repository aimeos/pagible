<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Enumerable;
use LaravelJsonApi\Contracts\Query\QueryParameters as QueryParametersContract;
use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Core\Support\Arr;
use UnexpectedValueException;
use function array_key_exists;
use function is_array;

class QueryParameters implements QueryParametersContract, Arrayable
{

    /**
     * @var IncludePaths|null
     */
    private ?IncludePaths $includePaths;

    /**
     * @var FieldSets|null
     */
    private ?FieldSets $fieldSets;

    /**
     * @var SortFields|null
     */
    private ?SortFields $sort;

    /**
     * @var array|null
     */
    private ?array $pagination;

    /**
     * @var FilterParameters|null
     */
    private ?FilterParameters $filters;

    /**
     * @var array
     */
    private array $unrecognised;

    /**
     * Fluent constructor.
     *
     * @return static
     */
    public static function make(): self
    {
        return new static();
    }

    /**
     * Cast a value to query parameters.
     *
     * @param QueryParametersContract|Enumerable|Request|array|null $value
     * @return static
     */
    public static function cast($value): self
    {
        if ($value instanceof static) {
            return $value;
        }

        if ($value instanceof QueryParametersContract) {
            return new static(
                $value->includePaths(),
                $value->sparseFieldSets(),
                $value->sortFields(),
                $value->page(),
                $value->filter(),
                $value->unrecognisedParameters(),
            );
        }

        if ($value instanceof Request) {
            return static::fromArray($value->query());
        }

        if (is_array($value) || $value instanceof Enumerable) {
            return static::fromArray($value);
        }

        if (is_null($value)) {
            return new static();
        }

        throw new UnexpectedValueException('Expecting a valid query parameters value.');
    }

    /**
     * @param array|Enumerable $value
     * @return static
     */
    public static function fromArray($value): self
    {
        if ($value instanceof Enumerable) {
            $value = $value->all();
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException('Expecting an array or enumerable value.');
        }

        $unrecognised = collect($value)->forget([
            'include',
            'fields',
            'sort',
            'page',
            'filter',
        ])->all();

        return new static(
            array_key_exists('include', $value) ? IncludePaths::cast($value['include']) : null,
            array_key_exists('fields', $value) ? FieldSets::cast($value['fields']) : null,
            array_key_exists('sort', $value) ? SortFields::cast($value['sort']) : null,
            array_key_exists('page', $value) ? $value['page'] : null,
            array_key_exists('filter', $value) ? FilterParameters::cast($value['filter']) : null,
            $unrecognised,
        );
    }

    /**
     * @param QueryParametersContract|Enumerable|array|null $value
     * @return static|null
     */
    public static function nullable($value): ?self
    {
        if (is_null($value)) {
            return null;
        }

        return static::cast($value);
    }

    /**
     * QueryParameters constructor.
     *
     * @param IncludePaths|null $includePaths
     * @param FieldSets|null $fieldSets
     * @param SortFields|null $sortFields
     * @param array|null $page
     * @param FilterParameters|null $filters
     * @param array|null $unrecognised
     */
    public function __construct(
        ?IncludePaths $includePaths = null,
        ?FieldSets $fieldSets = null,
        ?SortFields $sortFields = null,
        ?array $page = null,
        ?FilterParameters $filters = null,
        ?array $unrecognised = null
    ) {
        $this->includePaths = $includePaths;
        $this->fieldSets = $fieldSets;
        $this->sort = $sortFields;
        $this->pagination = $page;
        $this->filters = $filters;
        $this->unrecognised = $unrecognised ?? [];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return Arr::query($this->toQuery());
    }

    /**
     * @inheritDoc
     */
    public function includePaths(): ?IncludePaths
    {
        return $this->includePaths;
    }

    /**
     * @param IncludePaths|array|string|null $paths
     * @return $this
     */
    public function setIncludePaths($paths): self
    {
        $this->includePaths = IncludePaths::nullable($paths);

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutIncludePaths(): self
    {
        $this->includePaths = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function sparseFieldSets(): ?FieldSets
    {
        return $this->fieldSets;
    }

    /**
     * Set the sparse field sets.
     *
     * @param FieldSets|array|null $fieldSets
     * @return $this
     */
    public function setSparseFieldSets($fieldSets): self
    {
        $this->fieldSets = FieldSets::nullable($fieldSets);

        return $this;
    }

    /**
     * Remove all sparse field sets.
     *
     * @return $this
     */
    public function withoutSparseFieldSets(): self
    {
        $this->fieldSets = null;

        return $this;
    }

    /**
     * Add sparse fields by resource type.
     *
     * @param string $type
     * @param array $fields
     * @return $this
     */
    public function setFieldSet(string $type, array $fields): self
    {
        $this->fieldSets = FieldSets::cast($this->fieldSets)
            ->push(new FieldSet($type, $fields));

        return $this;
    }

    /**
     * Remove field sets by resource type.
     *
     * @param string ...$types
     * @return $this
     */
    public function withoutFieldSet(string ...$types): self
    {
        if ($this->fieldSets) {
            $this->fieldSets->forget(...$types);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function sortFields(): ?SortFields
    {
        return $this->sort;
    }

    /**
     * @param SortFields|array|string|null $fields
     * @return $this
     */
    public function setSortFields($fields): self
    {
        $this->sort = SortFields::nullable($fields);

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutSortFields(): self
    {
        $this->sort = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function page(): ?array
    {
        return $this->pagination;
    }

    /**
     * Set pagination.
     *
     * @param Arrayable|array|null $pagination
     * @return $this
     */
    public function setPagination($pagination): self
    {
        $this->pagination = is_null($pagination) ? null : collect($pagination)->toArray();

        return $this;
    }

    /**
     * Remove pagination.
     *
     * @return $this
     */
    public function withoutPagination(): self
    {
        $this->pagination = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function filter(): ?FilterParameters
    {
        return $this->filters;
    }

    /**
     * Set filters.
     *
     * @param mixed|null $filters
     * @return $this
     */
    public function setFilters($filters): self
    {
        $this->filters = FilterParameters::nullable($filters);

        return $this;
    }

    /**
     * Remove filters.
     *
     * @return $this
     */
    public function withoutFilters(): self
    {
        $this->filters = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function unrecognisedParameters(): array
    {
        return $this->unrecognised;
    }

    /**
     * Set unrecognised parameters.
     *
     * @param array|null $values
     * @return $this
     */
    public function setUnrecognisedParameters(?array $values): self
    {
        $this->unrecognised = $values ?? [];

        return $this;
    }

    /**
     * Remove unrecognised parameters.
     *
     * @return $this
     */
    public function withoutUnrecognisedParameters(): self
    {
        $this->unrecognised = [];

        return $this;
    }

    /**
     * @return array
     */
    public function toQuery(): array
    {
        $query = $this->unrecognisedParameters();

        if ($this->fieldSets) {
            $query['fields'] = $this->fieldSets->toArray();
        }

        if ($this->filters) {
            $query['filter'] = $this->filters->toArray();
        }

        if ($this->includePaths) {
            $query['include'] = $this->includePaths->toString();
        }

        if (is_array($this->pagination)) {
            $query['page'] = $this->pagination;
        }

        if ($this->sort) {
            $query['sort'] = $this->sort->toString();
        }

        ksort($query);

        return $query;
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        $query = $this->unrecognisedParameters();

        if ($this->fieldSets) {
            $query['fields'] = $this->fieldSets->toArray();
        }

        if ($this->filters) {
            $query['filter'] = $this->filters->toArray();
        }

        if ($this->includePaths) {
            $query['include'] = $this->includePaths->toArray();
        }

        if (is_array($this->pagination)) {
            $query['page'] = $this->pagination;
        }

        if ($this->sort) {
            $query['sort'] = $this->sort->toArray();
        }

        ksort($query);

        return $query;
    }

    /**
     * Get a new query parameter object with valid values for the provided schema.
     *
     * @param Schema $schema
     * @return static
     */
    public function forSchema(Schema $schema): self
    {
        $includePaths = IncludePaths::cast($this->includePaths)->forSchema($schema);
        $sortFields = SortFields::cast($this->sort)->forSchema($schema);
        $filters = FilterParameters::cast($this->filters)->forSchema($schema);
        $pagination = null;

        if ($this->pagination && $paginator = $schema->pagination()) {
            $pagination = collect($this->pagination)
                ->only($paginator->keys())
                ->all();
        }

        return new static(
            $includePaths->isNotEmpty() ? $includePaths : null,
            $this->fieldSets,
            $sortFields->isNotEmpty() ? $sortFields : null,
            !empty($pagination) ? $pagination : null,
            $filters->isNotEmpty() ? $filters : null,
            $this->unrecognised,
        );
    }
}
