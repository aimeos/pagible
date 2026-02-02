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

use Generator;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;
use LaravelJsonApi\Core\Document\ResourceIdentifier;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Schema\IdentifierInterface;
use Neomerx\JsonApi\Contracts\Schema\LinkInterface;
use Neomerx\JsonApi\Schema\Identifier;
use function iterator_to_array;

class Mapper
{

    /**
     * @var FactoryInterface
     */
    private FactoryInterface $factory;

    /**
     * Mapper constructor.
     *
     * @param FactoryInterface $factory
     */
    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Convert a Laravel JSON API resource identifier to a Neomerx identifier.
     *
     * @param ResourceIdentifier $identifier
     * @return IdentifierInterface
     */
    public function identifier(ResourceIdentifier $identifier): IdentifierInterface
    {
        return new Identifier(
            $identifier->id(),
            $identifier->type(),
            $meta = $identifier->hasMeta(),
            $meta ? $identifier->meta() : null
        );
    }

    /**
     * Convert Laravel JSON API links to Neomerx links.
     *
     * @param Links $links
     * @return Generator
     */
    public function links(Links $links): Generator
    {
        /** @var Link $link */
        foreach ($links as $link) {
            yield $link->key() => $this->link($link);
        }
    }

    /**
     * Convert Laravel JSON API links to an array of Neomerx links.
     *
     * @param Links $links
     * @return array
     */
    public function allLinks(Links $links): array
    {
        return iterator_to_array($this->links($links));
    }

    /**
     * Convert a Laravel JSON API link to a Neomerx link.
     *
     * @param Link $link
     * @return LinkInterface
     */
    public function link(Link $link): LinkInterface
    {
        return $this->factory->createLink(
            false,
            $link->href()->toString(),
            $meta = $link->hasMeta(),
            $meta ? $link->meta() : null
        );
    }
}
