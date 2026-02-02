<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Factory;

use LaravelJsonApi\Encoder\Neomerx\Parser\IdentifierAndResource;
use Neomerx\JsonApi\Contracts\Parser\EditableContextInterface;
use Neomerx\JsonApi\Contracts\Parser\ResourceInterface;
use Neomerx\JsonApi\Contracts\Schema\PositionInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaContainerInterface;
use Neomerx\JsonApi\Factories\Factory as BaseFactory;

class Factory extends BaseFactory
{

    /**
     * @inheritDoc
     */
    public function createParsedResource(
        EditableContextInterface $context,
        PositionInterface $position,
        SchemaContainerInterface $container,
        $data
    ): ResourceInterface
    {
        return new IdentifierAndResource($context, $position, $this, $container, $data);
    }
}
