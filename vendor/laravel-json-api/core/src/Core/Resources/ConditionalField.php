<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Resources;

use Closure;
use JsonSerializable;
use LaravelJsonApi\Contracts\Resources\Skippable;
use LogicException;

class ConditionalField implements JsonSerializable, Skippable
{

    /**
     * @var bool
     */
    private bool $check;

    /**
     * @var mixed
     */
    private $value;

    /**
     * ConditionalAttr constructor.
     *
     * @param bool $bool
     * @param mixed $value
     */
    public function __construct(bool $bool, $value)
    {
        $this->check = $bool;
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function __invoke()
    {
        if ($this->value instanceof Closure) {
            return ($this->value)();
        }

        return $this->value;
    }

    /**
     * Should the field be skipped when encoding?
     *
     * @return bool
     */
    public function skip(): bool
    {
        return false === $this->check;
    }

    /**
     * Get the field value.
     *
     * @return mixed
     */
    public function get()
    {
        if (false === $this->skip()) {
            return $this->value();
        }

        throw new LogicException('Conditional attribute must not be serialized.');
    }

    /**
     * Get the value without checking if it should be skipped.
     *
     * @return mixed
     */
    public function value()
    {
        return ($this)();
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->get();
    }

}
