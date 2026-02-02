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
use InvalidArgumentException;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Resources\Skippable;
use LogicException;
use Traversable;

class ConditionalFields implements IteratorAggregate, Skippable
{

    /**
     * @var bool
     */
    private bool $check;

    /**
     * @var Closure|iterable
     */
    private $values;

    /**
     * ConditionalAttrs constructor.
     *
     * @param bool $bool
     * @param Closure|iterable $values
     */
    public function __construct(bool $bool, $values)
    {
        if (!$values instanceof Closure && !is_iterable($values)) {
            throw new InvalidArgumentException('Expecting an iterable value or Closure.');
        }

        $this->check = $bool;
        $this->values = $values;
    }

    /**
     * Should the attributes be skipped when encoding?
     *
     * @return bool
     */
    public function skip(): bool
    {
        return false === $this->check;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        if (true === $this->skip()) {
            throw new LogicException('Conditional attributes must not be iterated.');
        }

        foreach ($this->values() as $key => $value) {
            if ($value instanceof Closure) {
                $value = ($value)();
            }

            yield $key => $value;
        }
    }

    /**
     * @return iterable
     */
    public function values(): iterable
    {
        if ($this->values instanceof Closure) {
            $values = ($this->values)();

            if (is_iterable($values)) {
                return $values;
            }

            throw new LogicException('Conditional attributes closure must return an iterable value.');
        }

        return $this->values;
    }

}
