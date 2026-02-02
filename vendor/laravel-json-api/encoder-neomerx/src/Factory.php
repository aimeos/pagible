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

use LaravelJsonApi\Contracts\Encoder\Encoder as EncoderContract;
use LaravelJsonApi\Contracts\Encoder\Factory as FactoryContract;
use LaravelJsonApi\Contracts\Server\Server;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;

class Factory implements FactoryContract
{

    /**
     * @var FactoryInterface
     */
    private FactoryInterface $factory;

    /**
     * Factory constructor.
     *
     * @param FactoryInterface $factory
     */
    public function __construct(FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @inheritDoc
     */
    public function build(Server $server): EncoderContract
    {
        return new Encoder(
            $server->resources(),
            $this->factory,
            new Mapper($this->factory),
            $server->jsonApi()
        );
    }
}
