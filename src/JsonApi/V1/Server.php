<?php

namespace Aimeos\Cms\JsonApi\V1;

use Illuminate\Support\Facades\Url;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use LaravelJsonApi\Core\Server\Server as BaseServer;
use LaravelJsonApi\Core\Document\JsonApi;


class Server extends BaseServer
{
    /**
     * Bootstrap the server when it is handling an HTTP request.
     *
     * @return void
     */
    public function serving(): void
    {
        \Aimeos\Cms\Models\Page::addGlobalScope( new \Aimeos\Cms\Scopes\Status() );
    }


    /**
     * Get the server's list of schemas.
     *
     * @return array
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
