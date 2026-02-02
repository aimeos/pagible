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
use CloudCreativity\JsonApi\Testing\Concerns\HasDocumentAssertions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonException;
use JsonSerializable;
use PHPUnit\Framework\Assert as PHPUnitAssert;
use RuntimeException;
use function json_decode;

/**
 * Class Document
 *
 * @package CloudCreativity\JsonApi\Testing
 */
class Document implements Arrayable, JsonSerializable, ArrayAccess
{
    use HasDocumentAssertions;

    /**
     * @var array
     */
    private array $document;

    /**
     * Safely create a document.
     *
     * @param Document|iterable|string $content
     * @return Document|null
     */
    public static function create($content): ?self
    {
        if (is_string($content)) {
            return self::fromString($content);
        }

        return $content ? self::cast($content) : null;
    }

    /**
     * Cast a document to an instance of this document class.
     *
     * @param Document|iterable|string $document
     * @return Document
     */
    public static function cast($document): self
    {
        if ($document instanceof self) {
            return $document;
        }

        if (is_string($document)) {
            return self::decode($document);
        }

        return self::fromIterable($document);
    }

    /**
     * Create a document from an iterable.
     *
     * @param iterable $input
     * @return Document
     */
    public static function fromIterable(iterable $input): self
    {
        return self::decode(
            Collection::make($input)->toJson()
        );
    }

    /**
     * Safely create a document from a string.
     *
     * @param string $json
     * @return Document|null
     */
    public static function fromString(string $json): ?self
    {
        try {
            return self::decode($json);
        } catch (RuntimeException $ex) {
            return null;
        }
    }

    /**
     * Decode a JSON string into a document.
     *
     * @param string $json
     * @return Document
     * @throws RuntimeException
     */
    public static function decode(string $json): self
    {
        try {
            $document = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new RuntimeException('Failed to decode JSON string.', 0, $ex);
        }

        if (is_array($document)) {
            return new self($document);
        }

        throw new RuntimeException('Expecting JSON to decode to an array.');
    }

    /**
     * Document constructor.
     *
     * @param array $document
     */
    public function __construct(array $document)
    {
        $this->document = $document;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return Collection::make($this->document)->offsetExists($offset);
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return Collection::make($this->document)->offsetGet($offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * Get a value from using a JSON pointer.
     *
     * @param string $pointer
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $pointer, $default = null)
    {
        if (!$path = Compare::path($pointer)) {
            return $this->document;
        }

        return Arr::get($this->document, $path, $default);
    }

    /**
     * Check if an item exists using JSON pointers.
     *
     * @param string ...$pointers
     * @return bool
     */
    public function has(string ...$pointers): bool
    {
        $paths = Collection::make($pointers)->map(function ($pointer) {
            return Compare::path($pointer);
        })->filter()->all();

        return Arr::has($this->document, $paths);
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return Compare::stringify($this->document);
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return $this->document;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return Compare::sort($this->document);
    }

    /**
     * Assert that all the provided members exist.
     *
     * @param string|string[] $pointers
     * @param string $message
     * @return $this
     */
    public function assertExists($pointers, string $message = ''): self
    {
        $missing = Collection::make((array) $pointers)->reject(function ($pointer) {
            return $this->has($pointer);
        })->implode(', ');

        PHPUnitAssert::assertEmpty($missing, $message ?: "Members [{$missing}] do not exist.");

        return $this;
    }

    /**
     * Assert that the provided members do not exist.
     *
     * @param string|string[] $pointers
     * @param string $message
     * @return Document
     */
    public function assertNotExists($pointers, string $message = ''): self
    {
        $unexpected = Collection::make((array) $pointers)->filter(function ($pointer) {
            return $this->has($pointer);
        })->implode(', ');

        PHPUnitAssert::assertEmpty($unexpected, $message ?: "Members [{$unexpected}] exist.");

        return $this;
    }

}
