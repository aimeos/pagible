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
use LaravelJsonApi\Contracts\Schema\Filter;
use LaravelJsonApi\Contracts\Schema\Schema;

class AllowedFilterParameters extends AbstractAllowedRule
{

    /**
     * Create an allowed filter parameters rule from the supplied schema.
     *
     * @param Schema $schema
     * @return static
     */
    public static function make(Schema $schema): self
    {
        return static::forFilters(...$schema->filters());
    }

    /**
     * @param Filter ...$filters
     * @return static
     */
    public static function forFilters(Filter ...$filters): self
    {
        return new self(Collection::make($filters)->map(
            fn(Filter $filter) => $filter->key()
        ));
    }

    /**
     * @inheritDoc
     */
    protected function extract($value): Collection
    {
        $value = is_array($value) ? $value : [];

        return Collection::make($value)->keys();
    }
}
