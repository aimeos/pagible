<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Status values of the pages.
 */
enum PageStatus : int
{
    /**
     * The page isn't available for visitors, its sub-pages are shown in its place in navigations
     */
    case Disabled = 0;

    /**
     * The page is available and shown in navigations
     */
    case Enabled = 1;

    /**
     * The page is available by its URL but not shown in navigations
     */
    case Hidden = 2;


    /**
     * Returns the status values of the pages which are available for visitors.
     *
     * @return list<int> Status values
     */
    public static function reachable() : array
    {
        return [self::Enabled->value, self::Hidden->value];
    }
}
