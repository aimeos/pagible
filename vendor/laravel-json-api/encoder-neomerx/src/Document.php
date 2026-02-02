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

use LaravelJsonApi\Contracts\Encoder\JsonApiDocument;
use LaravelJsonApi\Core\Document\JsonApi;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Json\Hash;
use LaravelJsonApi\Encoder\Neomerx\Encoder\Encoder as ExtendedEncoder;

abstract class Document implements JsonApiDocument
{
    /**
     * @var ExtendedEncoder
     */
    private ExtendedEncoder $encoder;

    /**
     * @var Mapper
     */
    private Mapper $mapper;

    /**
     * @var JsonApi|null
     */
    private ?JsonApi $jsonApi = null;

    /**
     * @var Links|null
     */
    private ?Links $links = null;

    /**
     * @var Hash|null
     */
    private ?Hash $meta = null;

    /**
     * JsonApiDocument constructor.
     *
     * @param ExtendedEncoder $encoder
     * @param Mapper $mapper
     */
    public function __construct(ExtendedEncoder $encoder, Mapper $mapper)
    {
        $this->encoder = $encoder;
        $this->mapper = $mapper;
    }

    /**
     * @return array
     */
    abstract protected function serialize(): array;

    /**
     * @return string
     */
    abstract protected function encode(): string;

    /**
     * @inheritDoc
     */
    public function withJsonApi($jsonApi): self
    {
        if ($value = JsonApi::nullable($jsonApi)) {
            $this->jsonApi = $value;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutJsonApi(): JsonApiDocument
    {
        $this->jsonApi = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withLinks($links): self
    {
        $this->links = Links::cast($links);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutLinks(): JsonApiDocument
    {
        $this->links = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withMeta($meta): self
    {
        $this->meta = Hash::cast($meta);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutMeta(): JsonApiDocument
    {
        $this->meta = null;

        return $this;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return json_decode(
            json: $this->toJson(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $this->prepareEncoder();

        return $this->serialize();
    }

    /**
     * @inheritDoc
     */
    public function toJson($options = 0)
    {
        $this->prepareEncoder();

        $this->encoder->withEncodeOptions($options | JSON_THROW_ON_ERROR);

        return $this->encode();
    }

    /**
     * Reset the encoder.
     *
     * @return void
     */
    private function prepareEncoder(): void
    {
        if ($this->jsonApi && $version = $this->jsonApi->version()) {
            $this->encoder->withJsonApiVersion($version);
        }

        if ($this->jsonApi && $this->jsonApi->hasMeta()) {
            $this->encoder->withJsonApiMeta($this->jsonApi->meta());
        }

        if ($this->meta && $this->meta->isNotEmpty()) {
            $this->encoder->withMeta($this->meta);
        }

        if ($this->links && $this->links->isNotEmpty()) {
            $this->encoder->withLinks($this->mapper->allLinks($this->links));
        }
    }

    /**
     * @return ExtendedEncoder
     */
    protected function encoder(): ExtendedEncoder
    {
        return $this->encoder;
    }

    /**
     * @return Mapper
     */
    protected function mapper(): Mapper
    {
        return $this->mapper;
    }
}
