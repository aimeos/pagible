<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Filters;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WherePivotIn extends WhereIn
{

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        if ($query instanceof BelongsToMany) {
            return $query->wherePivotIn(
                $this->column(),
                $this->deserialize($value)
            );
        }

        /**
         * If we haven't got a belongs-to-many, then we'll use a standard `whereIn()` and
         * hope that our column is qualified enough to be unique in the query so the
         * database knows we mean the pivot table.
         */
        return $query->whereIn(
            $this->qualifiedColumn(),
            $this->deserialize($value)
        );
    }
}
