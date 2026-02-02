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
use LaravelJsonApi\Contracts\Schema\Container;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Contracts\Schema\Schema;

class AllowedIncludePaths extends AbstractAllowedRule
{
    /**
     * Create an allowed include path rule for the supplied schema.
     *
     * @param Schema $schema
     * @return AllowedIncludePaths
     */
    public static function make(Schema $schema): self
    {
        return new self($schema->includePaths());
    }

    /**
     * Create an allowed include path rule for the relation's inverse resource types.
     *
     * @param Container $schemas
     * @param Relation $relation
     * @return static
     */
    public static function morphMany(Container $schemas, Relation $relation): self
    {
        $paths = Collection::make($relation->allInverse())
            ->map(static fn(string $resourceType) => Collection::make(
                $schemas->schemaFor($resourceType)->includePaths(),
            ))
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
