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

use ArrayAccess;
use Countable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use IteratorAggregate;
use LaravelJsonApi\Contracts\Serializable;
use LaravelJsonApi\Core\Json\Json;
use LogicException;
use Traversable;
use function count;
use function json_encode;
use function ksort;

class Links implements Serializable, IteratorAggregate, Countable, ArrayAccess
{
    use Concerns\Serializable;

    /**
     * @var array
     */
    private array $stack;

    /**
     * Create a JSON:API links object.
     *
     * @param Links|Link|iterable|null $value
     * @return Links
     */
    public static function cast($value): Links
    {
        if ($value instanceof Links) {
            return $value;
        }

        if (is_null($value)) {
            return new Links();
        }

        if ($value instanceof Link) {
            return new Links($value);
        }

        if (is_array($value)) {
            return Links::fromArray($value);
        }

        throw new LogicException('Unexpected links member value.');
    }

    /**
     * @param array $input
     * @return static
     */
    public static function fromArray(array $input): self
    {
        $links = new self();

        foreach ($input as $key => $link) {
            if (!$link instanceof Link) {
                $link = Link::fromArray($key, $link);
            }

            $links->push($link);
        }

        return $links;
    }

    /**
     * Links constructor.
     *
     * @param Link ...$links
     */
    public function __construct(Link ...$links)
    {
        $this->stack = [];
        $this->push(...$links);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->stack[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset): Link
    {
        return $this->stack[$offset];
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if ($value instanceof Link && $offset === $value->key()) {
            $this->push($value);
            return;
        }

        if ($value instanceof Link) {
            throw new InvalidArgumentException("Expecting link to have the key '$offset'.");
        }

        throw new InvalidArgumentException('Expecting a link object.');
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        unset($this->stack[$offset]);
    }

    /**
     * Get a link by its key.
     *
     * @param string $key
     * @return Link|null
     */
    public function get(string $key): ?Link
    {
        return $this->stack[$key] ?? null;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->stack[$key]);
    }

    /**
     * Get the "self" link.
     *
     * @return Link|null
     */
    public function getSelf(): ?Link
    {
        return $this->get('self');
    }

    /**
     * Does the "self" link exist?
     *
     * @return bool
     */
    public function hasSelf(): bool
    {
        return $this->has('self');
    }

    /**
     * Get the "related" link.
     *
     * @return Link|null
     */
    public function getRelated(): ?Link
    {
        return $this->get('related');
    }

    /**
     * Does the "related" link exist?
     *
     * @return bool
     */
    public function hasRelated(): bool
    {
        return $this->has('related');
    }

    /**
     * @return $this
     */
    public function relatedAsSelf(): self
    {
        $related = $this->getRelated();

        if ($related) {
            $this->push(new Link(
                key: 'self',
                href: $related->href(),
                meta: $related->meta(),
            ));
            return $this->forget('related');
        }

        return $this->forget('self');
    }

    /**
     * Push links into the collection.
     *
     * @param Link ...$links
     * @return $this
     */
    public function push(Link ...$links): self
    {
        foreach ($links as $link) {
            $this->stack[$link->key()] = $link;
        }

        ksort($this->stack);

        return $this;
    }

    /**
     * Put a link into the collection.
     *
     * @param string $key
     * @param LinkHref|string $href
     * @param mixed|null $meta
     * @return $this
     */
    public function put(string $key, $href, $meta = null)
    {
        $link = new Link($key, LinkHref::cast($href), Json::hash($meta));

        return $this->push($link);
    }

    /**
     * Merge the provided links.
     *
     * @param Links|iterable $links
     * @return $this
     */
    public function merge(iterable $links): self
    {
        foreach ($links as $key => $link) {
            if (!$link instanceof Link) {
                $link = Link::fromArray($key, $link);
            }

            $this->stack[$link->key()] = $link;
        }

        ksort($this->stack);

        return $this;
    }

    /**
     * Remove links.
     *
     * @param string ...$keys
     * @return $this
     */
    public function forget(string ...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->stack[$key]);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->stack);
    }

    /**
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->stack;
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return Collection::make($this->stack)->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        yield from $this->stack;
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->stack);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): ?array
    {
        return $this->stack ?: null;
    }

    /**
     * @inheritDoc
     */
    public function toJson($options = 0)
    {
        return json_encode($this, $options);
    }

}
