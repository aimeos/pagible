<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1;

use LaravelJsonApi\Core\Server\Server as BaseServer;


class Server extends BaseServer
{
    /**
     * Get the server's list of schemas.
     *
     * @return array<int, string>
     */
    protected function allSchemas(): array
    {
        return [Navs\NavSchema::class, Pages\PageSchema::class];
    }


    /**
     * Returns the base URL for generated links in the JSON API response.
     *
     * @return string Base URL
     */
    protected function baseUri(): string
    {
        return '/cms';
    }
}
