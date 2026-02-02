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

use JsonSerializable;
use LogicException;
use UnexpectedValueException;
use function collect;
use function http_build_query;
use function is_null;
use function is_string;
use function sprintf;

class LinkHref implements JsonSerializable
{

    /**
     * @var string
     */
    private string $uri;

    /**
     * @var array|null
     */
    private ?array $query = null;

    /**
     * @param LinkHref|static|string $value
     * @return static
     */
    public static function cast($value): self
    {
        if ($value instanceof static) {
            return $value;
        }

        if (is_string($value)) {
            return new self($value);
        }

        throw new LogicException('Unexpected link href.');
    }

    /**
     * LinkHref constructor.
     *
     * @param string $uri
     * @param iterable|null $query
     */
    public function __construct(string $uri, ?iterable $query = null)
    {
        if (empty($uri)) {
            throw new UnexpectedValueException('Expecting a non-empty string URI.');
        }

        $this->uri = $uri;

        if (!is_null($query)) {
            $this->setQuery($query);
        }
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
        if ($this->query) {
            return sprintf('%s?%s', $this->uri, http_build_query($this->query));
        }

        return $this->uri;
    }

    /**
     * @param iterable $query
     * @return $this
     */
    public function setQuery(iterable $query): self
    {
        $this->query = collect($query)->toArray() ?: null;

        return $this;
    }

    /**
     * @return $this
     */
    public function withoutQuery(): self
    {
        $this->query = null;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): string
    {
        return $this->toString();
    }

}
