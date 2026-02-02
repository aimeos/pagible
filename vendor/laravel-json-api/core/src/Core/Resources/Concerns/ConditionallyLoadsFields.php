<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources\Concerns;

use Closure;
use LaravelJsonApi\Core\Resources\ConditionalField;
use LaravelJsonApi\Core\Resources\ConditionalFields;
use function boolval;

trait ConditionallyLoadsFields
{

    /**
     * Conditionally include a field value.
     *
     * @param bool|mixed $check
     * @param mixed $value
     * @return ConditionalField
     */
    protected function when($check, $value): ConditionalField
    {
        return new ConditionalField(boolval($check), $value);
    }

    /**
     * Conditionally include a set of field values.
     *
     * @param bool|mixed $check
     * @param Closure|iterable $values
     * @return ConditionalFields
     */
    protected function mergeWhen($check, $values): ConditionalFields
    {
        return new ConditionalFields(boolval($check), $values);
    }
}
