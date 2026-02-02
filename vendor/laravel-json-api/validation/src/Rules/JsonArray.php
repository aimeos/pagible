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

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LaravelJsonApi\Validation\JsonApiValidation;

final class JsonArray implements ValidationRule
{
    /**
     * @inheritDoc
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value) || !array_is_list($value)) {
            $fail(JsonApiValidation::translationKeyForRule($this))->translate();
        }
    }
}