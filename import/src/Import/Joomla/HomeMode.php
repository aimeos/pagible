<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * How the Joomla home menu item uses the root page.
 *
 * @see \Aimeos\Cms\Concerns\Joomla\Home::homeMode()
 */
enum HomeMode
{
    /** The root page shows the category whose articles are imported below it */
    case Category;
    /** The root page is kept for other home pages like the featured articles */
    case Root;
    /** The article is imported into the root page */
    case Article;
}
