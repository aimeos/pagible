<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * How a Joomla menu item of the imported menu is imported.
 *
 * @see \Aimeos\Cms\Concerns\Joomla\Menu::menuItemMode()
 */
enum MenuItemMode
{
    /** Folder page of a menu heading or a list of all categories */
    case Heading;
    /** Link page of a URL or alias menu item */
    case Link;
    /** Skipped menu item guests can't see */
    case Restricted;
    /** Page of the linked article */
    case Article;
    /** Page of the shown category */
    case Category;
    /** Skipped menu item of an unsupported view */
    case Unsupported;
}
