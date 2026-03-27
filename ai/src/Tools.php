<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Prism\Prism\Facades\Tool;


class Tools
{
    /**
     * Returns the available tools.
     *
     * @return array<int, mixed>
     */
    public static function get(): array
    {
        return [
            Tool::make( Tools\SearchPages::class ),
            Tool::make( Tools\GetLocales::class ),
            Tool::make( Tools\AddPage::class ),
        ];
    }
}