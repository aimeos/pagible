<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec\Values;

use Illuminate\Support\Str;
use LaravelJsonApi\Core\Document\ErrorList;

abstract class Value
{

    /**
     * @var string
     */
    protected string $path;

    /**
     * @var ErrorList|null
     */
    private ?ErrorList $errors = null;

    /**
     * Validate the object.
     *
     * @return ErrorList
     */
    abstract protected function validate(): ErrorList;

    /**
     * @return ErrorList
     */
    public function errors(): ErrorList
    {
        if ($this->errors) {
            return $this->errors;
        }

        return $this->errors = $this->validate();
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return $this->errors()->isEmpty();
    }

    /**
     * @return bool
     */
    public function invalid(): bool
    {
        return !$this->valid();
    }

    /**
     * Get the path to the object that holds the value.
     *
     * @return string
     */
    protected function parent(): string
    {
        return Str::beforeLast($this->path, '/') ?: '/';
    }

    /**
     * Get the member name that holds the value.
     *
     * @return string
     */
    protected function member(): string
    {
        return Str::afterLast($this->path, '/');
    }
}
