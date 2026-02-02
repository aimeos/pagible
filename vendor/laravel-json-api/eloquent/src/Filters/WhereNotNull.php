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

class WhereNotNull extends WhereNull
{
    /**
     * @param bool $value
     * @return bool
     */
    protected function isWhereNull(bool $value): bool
    {
        return $value === false;
    }
}