<?php

namespace Aimeos\Cms\Mcp;

use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server;


#[Name('CMS Server')]
#[Version('1.0.0')]
#[Instructions('This server provides access to the content management system.')]
class CmsServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        \Aimeos\Cms\Tools\AddPage::class,
        \Aimeos\Cms\Tools\GetLocales::class,
        \Aimeos\Cms\Tools\GoogleQueries::class,
        \Aimeos\Cms\Tools\SearchPages::class,
    ];
}