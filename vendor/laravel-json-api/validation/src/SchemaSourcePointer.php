<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Validation;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;
use LaravelJsonApi\Contracts\Schema\Schema;
use function rtrim;

class SchemaSourcePointer implements JsonSerializable, Arrayable
{

    /**
     * @var Schema
     */
    private Schema $schema;

    /**
     * @var Collection
     */
    private Collection $parts;

    /**
     * @var string
     */
    private string $prefix = '';

    /**
     * @param Schema $schema
     * @param string $key
     * @return static
     */
    public static function make(Schema $schema, string $key): self
    {
        return new self($schema, $key);
    }

    /**
     * SourcePointer constructor.
     *
     * @param Schema $schema
     * @param string $key
     */
    public function __construct(Schema $schema, string $key)
    {
        $this->schema = $schema;
        $this->parts = collect(explode('.', $key));
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $fieldName = $this->fieldName();

        if ('type' === $fieldName || 'id' === $fieldName) {
            return $this->prefix . '/' . $fieldName;
        }

        if ($this->isAttribute()) {
            return $this->prefix . '/attributes/' . $this->parts->implode('/');
        }

        if ($this->isRelationship()) {
            $name = 1 < $this->parts->count() ?
                $fieldName . '/' . $this->parts->put(0, 'data')->implode('/') :
                $fieldName;

            return $this->prefix . "/relationships/{$name}";
        }

        return $this->prefix ? $this->prefix : '/';
    }

    /**
     * @param string $prefix
     * @return $this
     */
    public function withPrefix(string $prefix): self
    {
        $this->prefix = rtrim($prefix, '/');

        return $this;
    }

    /**
     * @return string
     */
    public function fieldName(): string
    {
        return $this->parts->first();
    }

    /**
     * @return bool
     */
    public function isAttribute(): bool
    {
        return $this->schema->isAttribute(
            $this->fieldName()
        );
    }

    /**
     * @return bool
     */
    public function isRelationship(): bool
    {
        return $this->schema->isRelationship(
            $this->fieldName()
        );
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return ['pointer' => $this->toString()];
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

}
