<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields\Relations;

class HasManyThrough extends ToMany
{

    /**
     * Create a has-many-through relation.
     *
     * @param string $fieldName
     * @param string|null $relation
     * @return HasManyThrough
     */
    public static function make(string $fieldName, ?string $relation = null): HasManyThrough
    {
        return new self($fieldName, $relation);
    }
}
