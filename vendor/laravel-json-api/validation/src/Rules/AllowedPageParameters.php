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

class AllowedPageParameters extends AbstractAllowedRule
{

    /**
     * Create an allowed page parameters rule for the supplied schema.
     *
     * @param Schema $schema
     * @return AllowedPageParameters
     */
    public static function make(Schema $schema): self
    {
        if ($paginator = $schema->pagination()) {
            return new self($paginator->keys());
        }

        return new self([]);
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
