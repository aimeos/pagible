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
            PrismaTools::laravel( Container::getInstance()->make( Tools\SearchPages::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\GetLocales::class ) ),
            PrismaTools::laravel( Container::getInstance()->make( Tools\AddPage::class ) ),
        ];
    }
}
