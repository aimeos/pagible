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

use Generator;
use IteratorAggregate;
use JsonSerializable;
use LaravelJsonApi\Contracts\Resources\Skippable;
use Traversable;
use function iterator_to_array;

class ConditionalIterator implements IteratorAggregate, JsonSerializable
{

    /**
     * @var iterable
     */
    private iterable $iterator;

    /**
     * ConditionalIterator constructor.
     *
     * @param iterable $iterator
     */
    public function __construct(iterable $iterator)
    {
        $this->iterator = $iterator;
    }

    /**
     * @return Generator
     */
    public function cursor(): Generator
    {
        foreach ($this->iterator as $key => $value) {
            if ($value instanceof Skippable && true === $value->skip()) {
                continue;
            }

            if ($value instanceof ConditionalFields) {
                yield from $value;
                continue;
            }

            yield $key => $value;
        }
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return iterator_to_array($this);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->cursor();
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): ?array
    {
        return $this->all() ?: null;
    }

}
