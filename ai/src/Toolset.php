<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Tools as CmsTools;


/**
 * MCP tool sets offered to the AI agents.
 */
final class Toolset
{
    /**
     * Discovery tools, needed once.
     */
    public const DISCOVERY = [
        CmsTools\GetLocales::class,
        CmsTools\GetSchemas::class,
    ];

    /**
     * Read-only tools, so injected content can't change or leak data.
     */
    public const READ = [
        CmsTools\GetPage::class,
        CmsTools\GetPageHistory::class,
        CmsTools\GetPageTree::class,
        CmsTools\SearchPages::class,
        CmsTools\GetElement::class,
        CmsTools\SearchElements::class,
        CmsTools\GetFile::class,
        CmsTools\SearchFiles::class,
        // provided by the optional metrics package
        CmsTools\GetPageMetrics::class,
    ];
}
