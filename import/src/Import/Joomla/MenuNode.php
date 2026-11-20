<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Joomla menu item at its position in the import order of the menu tree.
 *
 * Returned by Menu::tree().
 */
final readonly class MenuNode
{
    /**
     * Initializes the object.
     *
     * @param \stdClass $item Menu item
     * @param int|null $parent Position of the parent menu item in the import order or NULL for root items
     */
    public function __construct(
        public \stdClass $item,
        public ?int $parent = null
    ) {
    }
}
