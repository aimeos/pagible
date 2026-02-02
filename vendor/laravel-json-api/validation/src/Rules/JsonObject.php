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

final class JsonObject implements ValidationRule
{
    /**
     * @var bool
     */
    private bool $allowEmpty = false;

    /**
     * @param bool $allowEmpty
     * @return $this
     */
    public function allowEmpty(bool $allowEmpty = true): self
    {
        $this->allowEmpty = $allowEmpty;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->check($value)) {
            $fail(JsonApiValidation::translationKeyForRule($this))->translate();
        }
    }

    /**
     * Determine if the value was an object in JSON.
     *
     * @param mixed $value
     * @return bool
     */
    private function check(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($this->allowEmpty && empty($value)) {
            return true;
        }

        return !array_is_list($value);
    }
}