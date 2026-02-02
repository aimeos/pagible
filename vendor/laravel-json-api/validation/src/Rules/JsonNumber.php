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
use function is_float;
use function is_int;

class JsonNumber implements Rule
{
    /**
     * @var bool
     */
    private bool $onlyIntegers = false;

    /**
     * @return $this
     */
    public function onlyIntegers(): self
    {
        $this->onlyIntegers = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value): bool
    {
        if (true === $this->onlyIntegers) {
            return is_int($value);
        }

        return is_int($value) || is_float($value);
    }

    /**
     * @inheritDoc
     */
    public function message(): string
    {
        $key = $this->onlyIntegers ? 'json_integer' : 'json_number';

        return  trans(JsonApiValidation::qualifyTranslationKey($key));
    }
}
