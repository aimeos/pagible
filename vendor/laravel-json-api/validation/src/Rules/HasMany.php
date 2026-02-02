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

use Illuminate\Support\Arr;

class HasMany extends HasOne
{

    /**
     * @inheritDoc
     */
    protected function accept(?array $data): bool
    {
        if (is_null($data)) {
            return false;
        }

        if (empty($data)) {
            return true;
        }

        if (Arr::isAssoc($data)) {
            return false;
        }

        return collect($data)->every(function ($value) {
            return $this->acceptType($value);
        });
    }
}
