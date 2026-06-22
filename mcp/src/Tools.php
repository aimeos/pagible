<?php

/**
 * @license MIT, https://opensource.org/license/mit
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
            PrismaTools::laravel( Tools\AddElement::class ),
            PrismaTools::laravel( Tools\AddFile::class ),
            PrismaTools::laravel( Tools\AddPage::class ),
            PrismaTools::laravel( Tools\DropElement::class ),
            PrismaTools::laravel( Tools\DropFile::class ),
            PrismaTools::laravel( Tools\DropPage::class ),
            PrismaTools::laravel( Tools\GetElement::class ),
            PrismaTools::laravel( Tools\GetFile::class ),
            PrismaTools::laravel( Tools\GetLocales::class ),
            PrismaTools::laravel( Tools\GetPage::class ),
            PrismaTools::laravel( Tools\GetPageHistory::class ),
            PrismaTools::laravel( Tools\GetPageMetrics::class ),
            PrismaTools::laravel( Tools\GetPageTree::class ),
            PrismaTools::laravel( Tools\GetSchemas::class ),
            PrismaTools::laravel( Tools\MovePage::class ),
            PrismaTools::laravel( Tools\PublishElement::class ),
            PrismaTools::laravel( Tools\PublishFile::class ),
            PrismaTools::laravel( Tools\PublishPage::class ),
            PrismaTools::laravel( Tools\RestoreElement::class ),
            PrismaTools::laravel( Tools\RestoreFile::class ),
            PrismaTools::laravel( Tools\RestorePage::class ),
            PrismaTools::laravel( Tools\SaveElement::class ),
            PrismaTools::laravel( Tools\SaveFile::class ),
            PrismaTools::laravel( Tools\SavePage::class ),
            PrismaTools::laravel( Tools\SearchElements::class ),
            PrismaTools::laravel( Tools\SearchFiles::class ),
            PrismaTools::laravel( Tools\SearchPages::class ),
        ];
    }
}
