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

use Illuminate\Contracts\Validation\Rule;
use LaravelJsonApi\Validation\JsonApiValidation;
use function filter_var;
use function is_bool;

class JsonBoolean implements Rule
{
    /**
     * @var bool
     */
    private bool $asString = false;

    /**
     * Mark the rule as validating a string boolean (e.g. from query parameters).
     *
     * @return $this
     */
    public function asString(): self
    {
        $this->asString = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value): bool
    {
        if (true === $this->asString) {
            return is_bool(filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ));
        }

        return is_bool($value);
    }

    /**
     * @inheritDoc
     */
    public function message(): string
    {
        $key = $this->asString ?
            JsonApiValidation::qualifyTranslationKey('boolean_string') :
            JsonApiValidation::translationKeyForRule($this);

        return trans($key);
    }
}
