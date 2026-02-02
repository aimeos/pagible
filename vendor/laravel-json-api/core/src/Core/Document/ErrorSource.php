<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Document;

use LaravelJsonApi\Contracts\Serializable;
use LogicException;
use function array_filter;
use function is_array;
use function is_null;

class ErrorSource implements Serializable
{
    use Concerns\Serializable;

    /**
     * @var string|null
     */
    private ?string $pointer;

    /**
     * @var string|null
     */
    private ?string $parameter;

    /**
     * @var string|null
     */
    private ?string $header;

    /**
     * @param ErrorSource|array|null $value
     * @return ErrorSource
     */
    public static function cast($value): self
    {
        if (is_null($value)) {
            return new self();
        }

        if ($value instanceof self) {
            return $value;
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        throw new LogicException('Unexpected error source value.');
    }

    /**
     * @param array $source
     * @return ErrorSource
     */
    public static function fromArray(array $source): self
    {
        return new self(
            $source['pointer'] ?? null,
            $source['parameter'] ?? null,
            $source['header'] ?? null,
        );
    }

    /**
     * ErrorSource constructor.
     *
     * @param string|null $pointer
     * @param string|null $parameter
     * @param string|null $header
     */
    public function __construct(?string $pointer = null, ?string $parameter = null, ?string $header = null)
    {
        $this->pointer = $pointer;
        $this->parameter = $parameter;
        $this->header = $header;
    }

    /**
     * The JSON Pointer [RFC6901] to the associated entity in the request document.
     *
     * E.g. "/data" for a primary data object, or "/data/attributes/title" for a specific attribute.
     *
     * @return string|null
     */
    public function pointer(): ?string
    {
        return $this->pointer;
    }

    /**
     * Add a JSON Pointer.
     *
     * @param string|null $pointer
     * @return $this
     */
    public function setPointer(?string $pointer): self
    {
        $this->pointer = $pointer;

        return $this;
    }

    /**
     * Remove the JSON pointer.
     *
     * @return $this
     */
    public function withoutPointer(): self
    {
        $this->pointer = null;

        return $this;
    }


    /**
     * A string indicating which URI query parameter caused the error.
     *
     * @return string|null
     */
    public function parameter(): ?string
    {
        return $this->parameter;
    }

    /**
     * Add a string indicating which URI query parameter caused the error.
     *
     * @param string|null $parameter
     * @return $this
     */
    public function setParameter(?string $parameter): self
    {
        $this->parameter = $parameter;

        return $this;
    }

    /**
     * Remove the source parameter.
     *
     * @return $this
     */
    public function withoutParameter(): self
    {
        $this->parameter = null;

        return $this;
    }

    /**
     * A string indicating which request header caused the error.
     *
     * @return string|null
     */
    public function header(): ?string
    {
        return $this->header;
    }

    /**
     * Add a string indicating which request header caused the error.
     *
     * @param string|null $header
     * @return $this
     */
    public function setHeader(?string $header): self
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Remove the source header.
     *
     * @return $this
     */
    public function withoutHeader(): self
    {
        $this->header = null;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->pointer) && empty($this->parameter) && empty($this->header);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return array_filter([
            'parameter' => $this->parameter,
            'pointer' => $this->pointer,
            'header' => $this->header,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): ?array
    {
        return $this->toArray() ?: null;
    }

}
