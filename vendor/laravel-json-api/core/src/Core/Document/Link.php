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

use InvalidArgumentException;
use LaravelJsonApi\Contracts\Serializable;
use LaravelJsonApi\Core\Json\Json;

class Link implements Serializable
{

    use Concerns\HasMeta;
    use Concerns\Serializable;

    /**
     * @var string
     */
    private string $key;

    /**
     * @var LinkHref
     */
    private LinkHref $href;

    /**
     * @param string $key
     * @param array|string $value
     * @return Link
     */
    public static function fromArray(string $key, $value): self
    {
        if (is_array($value) && isset($value['href'])) {
            return new self(
                $key,
                $value['href'],
                Json::hash($value['meta'] ?? [])
            );
        }

        return new self($key, $value);
    }

    /**
     * Link constructor.
     *
     * @param string $key
     * @param LinkHref|string $href
     * @param mixed|null $meta
     */
    public function __construct(string $key, $href, $meta = null)
    {
        if (empty($key)) {
            throw new InvalidArgumentException('Expecting key to be a non-empty string.');
        }

        $this->key = $key;
        $this->href = LinkHref::cast($href);
        $this->setMeta($meta);
    }

    /**
     * @return string
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return LinkHref
     */
    public function href(): LinkHref
    {
        return $this->href;
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        $arr = ['href' => $this->href->toString()];

        if ($this->hasMeta()) {
            $arr['meta'] = $this->meta->toArray();
        }

        return $arr;
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        if ($this->doesntHaveMeta()) {
            return $this->href;
        }

        return [
            'href' => $this->href,
            'meta' => $this->meta,
        ];
    }

}
