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

class ParameterNotSupported implements Rule
{

    /**
     * @var string|null
     */
    private ?string $name;

    /**
     * DisallowedParameter constructor.
     *
     * @param string|null $name
     */
    public function __construct(?string $name = null)
    {
        $this->name = $name;
    }

    /**
     * @inheritDoc
     */
    public function passes($attribute, $value)
    {
        if (!$this->name) {
            $this->name = $attribute;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function message()
    {
        return trans(JsonApiValidation::translationKeyForRule($this), [
            'name' => $this->name,
        ]);
    }

}
