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
use LaravelJsonApi\Contracts\Schema\Schema;

class AllowedSortParameters extends AbstractAllowedRule
{

    /**
     * Create an allowed sort parameter rule for the supplied schema.
     *
     * @param Schema $schema
     * @return static
     */
    public static function make(Schema $schema): self
    {
        return new self($schema->sortFields());
    }

    /**
     * @inheritDoc
     */
    protected function extract($value): Collection
    {
        $params = (is_string($value) && !empty($value)) ? explode(',', $value) : [];

        return Collection::make($params)->map(function ($param) {
            return ltrim($param, '+-');
        })->unique()->values();
    }

}
