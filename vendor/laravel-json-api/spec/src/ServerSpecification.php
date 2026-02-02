<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Spec;

use LaravelJsonApi\Contracts\Server\Server;

class ServerSpecification implements Specification
{

    /**
     * @var Server
     */
    private Server $server;

    /**
     * ServerSpecification constructor.
     *
     * @param Server $server
     */
    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * @inheritDoc
     */
    public function clientIds(string $resourceType): bool
    {
        return $this->server
            ->schemas()
            ->schemaFor($resourceType)
            ->id()
            ->acceptsClientIds();
    }

    /**
     * @inheritDoc
     */
    public function exists(string $resourceType, string $resourceId): bool
    {
        return $this->server
            ->store()
            ->exists($resourceType, $resourceId);
    }

    /**
     * @inheritDoc
     */
    public function fields(string $resourceType): iterable
    {
        $schema = $this->server
            ->schemas()
            ->schemaFor($resourceType);

        yield from $schema->attributes();
        yield from $schema->relationships();
    }

    /**
     * @inheritDoc
     */
    public function types(): array
    {
        return $this->server
            ->schemas()
            ->types();
    }

}
