<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation\Rules;

use Illuminate\Support\Collection;
use LaravelJsonApi\Contracts\Implementations\Countable\CountableSchema;
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Contracts\Schema\Schema as SchemaContract;
use function collect;
use function explode;
use function is_string;

class AllowedCountableFields extends AbstractAllowedRule
{

    /**
     * Create an allowed countable fields rule for the supplied schema.
     *
     * @param SchemaContract $schema
     * @return $this
     */
    public static function make(SchemaContract $schema): self
    {
        if ($schema instanceof CountableSchema) {
            return new self($schema->countable());
        }

        return new self();
    }

    /**
     * Create an allowed countable fields rule for the relation's inverse resource types.
     *
     * @param Container $schemas
     * @param Relation $relation
     * @return static
     */
    public static function morphMany(Container $schemas, Relation $relation): self
    {
        $paths = collect($relation->allInverse())
            ->map(fn(string $resourceType) => $schemas->schemaFor($resourceType))
            ->whereInstanceOf(CountableSchema::class)
            ->map(fn(CountableSchema $schema) => collect($schema->countable()))
            ->flatten();

        return new self($paths);
    }

    /**
     * @inheritDoc
     */
    protected function extract($value): Collection
    {
        $paths = (is_string($value) && !empty($value)) ? explode(',', $value) : [];

        return new Collection($paths);
    }
}
