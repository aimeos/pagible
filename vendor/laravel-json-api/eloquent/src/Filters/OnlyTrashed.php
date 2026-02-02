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

use LogicException;

class OnlyTrashed extends WithTrashed
{

    /**
     * @inheritDoc
     */
    public function apply($query, $value)
    {
        if (false === $this->deserialize($value)) {
            return $query;
        }

        if (is_callable([$query, 'onlyTrashed'])) {
            return $query->onlyTrashed();
        }

        throw new LogicException("Filter {$this->key()} expects query builder to have a `withTrashed` method.");
    }

}
