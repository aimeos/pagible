<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Aimeos\Prisma\Tools as PrismaTools;
use Illuminate\Container\Container;


class Tools
{
    /**
     * Returns the available tools.
     *
     * @return array<int, \Aimeos\Prisma\Tools\Adapter\Adapter>
     */
    public static function get(): array
    {
        return [
            PrismaTools::laravel( Container::getInstance()->make( Tools\AddElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\AddFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\AddPage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\DropElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\DropFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\DropPage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetLocales::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetPage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetPageHistory::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetPageMetrics::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetPageTree::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetSchemas::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\MovePage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\PublishElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\PublishFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\PublishPage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\RestoreElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\RestoreFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\RestorePage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SaveElement::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SaveFile::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SavePage::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SearchElements::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SearchFiles::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\SearchPages::class ) ),
        ];
    }
}
