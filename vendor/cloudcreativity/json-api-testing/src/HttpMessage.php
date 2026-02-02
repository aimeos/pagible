<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing;

use ArrayAccess;

/**
 * Class HttpMessage
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class HttpMessage implements ArrayAccess
{
    use Concerns\HasHttpAssertions;

    /**
     * @var int
     */
    protected int $status;

    /**
     * @var string|null
     */
    protected ?string $contentType;

    /**
     * @var string|null
     */
    protected ?string $content;

    /**
     * @var array
     */
    protected array $headers;

    /**
     * HttpMessage constructor.
     *
     * @param int $status
     * @param string|null $contentType
     * @param string|null $content
     * @param array $headers
     */
    public function __construct(
        int $status,
        ?string $contentType = null,
        ?string $content = null,
        array $headers = []
    ) {
        $this->status = $status;
        $this->contentType = $contentType;
        $this->content = $content;
        $this->headers = $headers;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return $this->getDocument()->offsetExists($offset);
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->getDocument()->offsetGet($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        $this->getDocument()->offsetSet($offset, $value);
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        $this->getDocument()->offsetUnset($offset);
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Return a new instance with a new status code.
     *
     * @param int $status
     * @return HttpMessage
     */
    public function withStatusCode(int $status): self
    {
        $copy = clone $this;
        $copy->status = $status;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getContentType(): ?string
    {
        return $this->contentType;
    }

    /**
     * Return a new instance with the supplied content type.
     *
     * @param string|null $contentType
     * @return HttpMessage
     */
    public function withContentType(?string $contentType): self
    {
        $copy = clone $this;
        $copy->contentType = $contentType;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Return a new instance with the supplied content.
     *
     * @param string|null $content
     * @return $this
     */
    public function withContent(?string $content): self
    {
        $copy = clone $this;
        $copy->content = $content;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getContentLocation(): ?string
    {
        return $this->headers['Content-Location'] ?? null;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->headers['Location'] ?? null;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Return a new instance with the provided headers.
     *
     * @param array $headers
     * @return $this
     */
    public function withHeaders(array $headers): self
    {
        $copy = clone $this;
        $copy->headers = $headers;

        return $copy;
    }

    /**
     * Return a new instance with the provided header.
     *
     * @param string $name
     * @param string $value
     * @return $this
     */
    public function withHeader(string $name, string $value): self
    {
        $copy = clone $this;
        $copy->headers[$name] = $value;

        return $copy;
    }
}
