<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;


class Tools
{
    /**
     * Returns the available tools.
     *
     * @return array List of tool objects
     */
    public static function get(): array
    {
        return [
            new Tools\Pages(),
            new Tools\Locales(),
            new Tools\AddPage(),
        ];
    }
}