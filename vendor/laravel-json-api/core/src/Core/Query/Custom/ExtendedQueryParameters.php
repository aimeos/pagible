<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Query\Custom;

use LaravelJsonApi\Contracts\Schema\Schema;
use LaravelJsonApi\Core\Query\FieldSets;
use LaravelJsonApi\Core\Query\FilterParameters;
use LaravelJsonApi\Core\Query\IncludePaths;
use LaravelJsonApi\Core\Query\QueryParameters;
use LaravelJsonApi\Core\Query\SortFields;

class ExtendedQueryParameters extends QueryParameters
{

    /**
     * The `withCount` parameter key.
     *
     * @var string
     */
    private static string $withCount = 'withCount';

    /**
     * Get or set the query parameter key for the countable paths.
     *
     * @param string|null $name
     * @return string
     */
    public static function withCount(?string $name = null): string
    {
        if (empty($name)) {
            return self::$withCount;
        }

        return self::$withCount = $name;
    }

    /**
     * @var CountablePaths|null
     */
    private ?CountablePaths $countable;

    /**
     * ExtendedQueryParameters constructor.
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
        parent::__construct(
            $includePaths,
            $fieldSets,
            $sortFields,
            $page,
            $filters,
            collect($unrecognised)->forget(self::$withCount)->all() ?: null,
        );

        $this->countable = CountablePaths::nullable($unrecognised[self::$withCount] ?? null);
    }

    /**
     * Get the countable relationships.
     *
     * @return CountablePaths|null
     */
    public function countable(): ?CountablePaths
    {
        return $this->countable;
    }

    /**
     * Set the countable relationships.
     *
     * @param mixed $countable
     * @return $this
     */
    public function setCountable($countable): self
    {
        $this->countable = CountablePaths::nullable($countable);

        return $this;
    }

    /**
     * Remove countable paths.
     *
     * @return $this
     */
    public function withoutCountable(): self
    {
        $this->countable = null;

        return $this;
    }

    /**
     * @return array
     */
    public function unrecognisedParameters(): array
    {
        $parameters = parent::unrecognisedParameters();
        $countable = $this->countable();

        if ($countable) {
            $parameters[self::$withCount] = $countable->toString();
        }

        return $parameters;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $values = parent::toArray();

        if (isset($values[self::$withCount])) {
            $values[self::$withCount] = $this->countable()->toArray();
        }

        return $values;
    }

    /**
     * @param Schema $schema
     * @return static
     */
    public function forSchema(Schema $schema): QueryParameters
    {
        $copy = parent::forSchema($schema);
        $countable = CountablePaths::cast($this->countable)->forSchema($schema);

        $copy->setCountable(
            $countable->isNotEmpty() ? $countable : null
        );

        return $copy;
    }
}
