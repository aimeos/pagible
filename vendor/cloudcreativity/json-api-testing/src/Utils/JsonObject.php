<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing\Utils;

use ArrayAccess;
use Countable;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use RuntimeException;
use UnexpectedValueException;
use function is_array;
use function is_int;
use function is_string;

final class JsonObject implements Jsonable, JsonSerializable, Arrayable, Countable, ArrayAccess
{
    use JsonToArray;

    /**
     * @var array|JsonSerializable
     */
    private $value;

    /**
     * Cast a value to a JSON object.
     *
     * @param JsonSerializable|array|UrlRoutable|string|int $value
     * @param string $type
     * @return self
     */
    public static function cast($value, string $type = ''): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (self::identifiable($value)) {
            return self::fromId($type, $value);
        }

        if (is_array($value)) {
            return self::fromArray($value);
        }

        if ($value instanceof JsonSerializable) {
            return new self($value);
        }

        throw new UnexpectedValueException(
            'Expecting a JSON serializable object, array, string, integer or url routable object.'
        );
    }

    /**
     * Cast a value to a JSON object or null.
     *
     * @param JsonSerializable|array|UrlRoutable|string|int|null $value
     * @param string $type
     * @return self|null
     */
    public static function nullable($value, string $type = ''): ?self
    {
        if (null === $value) {
            return null;
        }

        return self::cast($value, $type);
    }

    /**
     * Create a JSON object from a resource type and id.
     *
     * @param string $type
     * @param UrlRoutable|string|int $id
     * @return self
     */
    public static function fromId(string $type, $id): self
    {
        if (empty($type)) {
            throw new LogicException('You must set an expected resource type on your test.');
        }

        if ($id instanceof UrlRoutable) {
            $id = $id->getRouteKey();
        }

        if (is_string($id) || is_int($id)) {
            return new self([
                'type' => $type,
                'id' => (string) $id,
            ]);
        }

        throw new InvalidArgumentException('Expecting id to be a string, integer or UrlRoutable object.');
    }

    /**
     * @param array $value
     * @return self
     */
    public static function fromArray(array $value): self
    {
        if (isset($value['id']) && $value['id'] instanceof UrlRoutable) {
            $value['id'] = (string) $value['id']->getRouteKey();
        } else if (isset($value['id']) && is_int($value['id'])) {
            $value['id'] = (string) $value['id'];
        }

        return new self($value);
    }

    /**
     * Fluent constructor.
     *
     * @param array|JsonSerializable $value
     * @return self
     */
    public static function make($value): self
    {
        return new self($value);
    }

    /**
     * JsonObject constructor.
     *
     * @param array|JsonSerializable $value
     */
    public function __construct($value)
    {
        if (!is_array($value) && !$value instanceof JsonSerializable) {
            throw new InvalidArgumentException('Expecting an array or JSON serializable object.');
        }

        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        if ($this->value instanceof ArrayAccess) {
            return $this->value->offsetExists($offset);
        }

        $value = is_array($this->value) ? $this->value : $this->toArray();

        return isset($value[$offset]);
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if ($this->value instanceof ArrayAccess) {
            return $this->value->offsetGet($offset);
        }

        $value = is_array($this->value) ? $this->value : $this->toArray();

        return $value[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if ($this->value instanceof ArrayAccess) {
            $this->value->offsetSet($offset, $value);
        }

        if (is_array($this->value)) {
            $this->value[$offset] = $value;
        }

        throw new RuntimeException('Value is not an array or object implementing array access.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        if ($this->value instanceof ArrayAccess) {
            $this->value->offsetUnset($offset);
        }

        if (is_array($this->value)) {
            unset($this->value[$offset]);
        }

        throw new RuntimeException('Value is not an array or object implementing array access.');
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        if (is_array($this->value) || $this->value instanceof Countable) {
            return count($this->value);
        }

        return count($this->toArray());
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
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
    public function toJson($options = 0)
    {
        if ($this->value instanceof Jsonable) {
            return $this->value->toJson($options);
        }

        return json_encode($this, $options);
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        if ($this->value instanceof JsonSerializable) {
            return $this->value->jsonSerialize();
        }

        return $this->value;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    private static function identifiable($value): bool
    {
        if ($value instanceof UrlRoutable) {
            return true;
        }

        return is_int($value) || is_string($value);
    }
}
