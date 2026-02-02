<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Schema\Concerns;

trait RequiredForValidation
{

    /**
     * @var bool
     */
    private bool $validated = false;

    /**
     * Mark the relation as required for validation.
     *
     * @return $this
     */
    public function mustValidate(): self
    {
        $this->validated = true;

        return $this;
    }

    /**
     * Mark the relation as not required for validation.
     *
     * @return $this
     */
    public function notValidated(): self
    {
        $this->validated = false;

        return $this;
    }

    /**
     * @return bool
     */
    public function isValidated(): bool
    {
        return $this->validated;
    }
}
