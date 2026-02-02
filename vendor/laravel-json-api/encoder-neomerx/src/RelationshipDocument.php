<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx;

use LaravelJsonApi\Core\Resources\JsonApiResource;
use LaravelJsonApi\Encoder\Neomerx\Encoder\Encoder as ExtendedEncoder;

class RelationshipDocument extends Document
{
    /**
     * @var JsonApiResource
     */
    protected JsonApiResource $resource;

    /**
     * @var string
     */
    protected string $fieldName;

    /**
     * @var mixed
     */
    protected $data;

    /**
     * RelationshipDocument constructor.
     *
     * @param ExtendedEncoder $encoder
     * @param Mapper $mapper
     * @param JsonApiResource $resource
     * @param string $fieldName
     * @param object|iterable|null $data
     */
    public function __construct(
        ExtendedEncoder $encoder,
        Mapper $mapper,
        JsonApiResource $resource,
        string $fieldName,
        $data
    ) {
        parent::__construct($encoder, $mapper);
        $this->resource = $resource;
        $this->fieldName = $fieldName;
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    protected function serialize(): array
    {
        return $this
            ->encoder()
            ->serializeIdentifiers($this->data);
    }

    /**
     * @inheritDoc
     */
    protected function encode(): string
    {
        return $this
            ->encoder()
            ->encodeIdentifiers($this->data);
    }
}
