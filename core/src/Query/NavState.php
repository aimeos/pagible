<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;


/**
 * Navigation states of the pages pruned by NavTree::prune().
 */
enum NavState : int
{
    /**
     * The page is left out and its sub-pages take its place, e.g. for disabled pages
     */
    case Skip = 0;

    /**
     * The page is shown
     */
    case Show = 1;

    /**
     * The page is left out including its sub-pages, e.g. for hidden pages
     */
    case Hide = 2;
}
