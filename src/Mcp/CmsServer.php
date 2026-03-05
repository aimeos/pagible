<?php

namespace Aimeos\Cms\Mcp;

use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server;


#[Name('CMS Server')]
#[Version('1.1.0')]
#[Instructions('This server provides access to the content management system.')]
class CmsServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        // Read tools - Discovery & configuration
        \Aimeos\Cms\Tools\GetLocales::class,
        \Aimeos\Cms\Tools\GetSchemas::class,

        // Read tools - Pages
        \Aimeos\Cms\Tools\GetPage::class,
        \Aimeos\Cms\Tools\GetPageTree::class,
        \Aimeos\Cms\Tools\GetPageHistory::class,
        \Aimeos\Cms\Tools\GetPageMetrics::class,
        \Aimeos\Cms\Tools\ListPages::class,
        \Aimeos\Cms\Tools\SearchPages::class,

        // Read tools - Elements
        \Aimeos\Cms\Tools\GetElement::class,
        \Aimeos\Cms\Tools\ListElements::class,
        \Aimeos\Cms\Tools\SearchElements::class,

        // Read tools - Files
        \Aimeos\Cms\Tools\GetFile::class,
        \Aimeos\Cms\Tools\ListFiles::class,
        \Aimeos\Cms\Tools\SearchFiles::class,

        // Write tools - Pages
        \Aimeos\Cms\Tools\AddPage::class,
        \Aimeos\Cms\Tools\UpdatePage::class,
        \Aimeos\Cms\Tools\PublishPage::class,
        \Aimeos\Cms\Tools\DropPage::class,
        \Aimeos\Cms\Tools\RestorePage::class,
        \Aimeos\Cms\Tools\MovePage::class,

        // Write tools - Elements
        \Aimeos\Cms\Tools\AddElement::class,
        \Aimeos\Cms\Tools\UpdateElement::class,
        \Aimeos\Cms\Tools\PublishElement::class,
        \Aimeos\Cms\Tools\DropElement::class,
        \Aimeos\Cms\Tools\RestoreElement::class,

        // Write tools - Files
        \Aimeos\Cms\Tools\AddFile::class,
        \Aimeos\Cms\Tools\UpdateFile::class,
        \Aimeos\Cms\Tools\PublishFile::class,
        \Aimeos\Cms\Tools\DropFile::class,
        \Aimeos\Cms\Tools\RestoreFile::class,

        // AI tools
        \Aimeos\Cms\Tools\RefineContent::class,
        \Aimeos\Cms\Tools\TranslateContent::class,
    ];
}