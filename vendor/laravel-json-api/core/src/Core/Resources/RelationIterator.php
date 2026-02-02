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
use LaravelJsonApi\Contracts\Resources\JsonApiRelation;
use RuntimeException;
use Traversable;

class RelationIterator implements IteratorAggregate
{
    /**
     * @var JsonApiResource
     */
    private JsonApiResource $resource;

    /**
     * RelationIterator constructor.
     *
     * @param JsonApiResource $resource
     */
    public function __construct(JsonApiResource $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return $this->cursor();
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return iterator_to_array($this);
    }

    /**
     * @return Generator
     */
    public function cursor(): Generator
    {
        foreach ($this->values() as $value) {
            if ($value instanceof ConditionalField) {
                $value = $value->value();
            }

            if ($value instanceof JsonApiRelation) {
                yield $value->fieldName() => $value;
                continue;
            }

            throw new RuntimeException('Expecting a JSON:API resource relation.');
        }
    }

    /**
     * @return Generator
     */
    private function values(): Generator
    {
        foreach ($this->resource->relationships(null) as $value) {
            if ($value instanceof ConditionalFields) {
                yield from $value->values();
                continue;
            }

            yield $value;
        }
    }

}
