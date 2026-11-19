<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Menu items and routes Links tests the targets of URL and alias menu items with, before the URLs of the pages are known.
 *
 * @see Links::linkable()
 */
final readonly class LinkCheck
{
    /**
     * Initializes the object.
     *
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param (\Closure(int|string): bool)|null $variant Tests if the translated menu item of another menu has a page variant
     *  because it translates a menu item of the imported menu with own page, see Urls::menuVariant(), or NULL if unknown
     */
    public function __construct(
        public Menu $menu,
        public Collection $menuItems,
        public Router $router,
        public ?\Closure $variant = null
    ) {
    }


    /**
     * Returns the check for the URL or alias menu item, which can only point to page variants if it isn't part of the imported menu.
     *
     * The menu items of the imported menu can't point to page variants because CategoryItems selects their pages before the
     * variants are known.
     */
    public function for( \stdClass $menuItem ): self
    {
        if( $this->variant === null || $this->menu->item( $menuItem->id ?? 0 ) === null ) {
            return $this;
        }

        return new self( $this->menu, $this->menuItems, $this->router );
    }


    /**
     * Returns the published menu item of the imported menu or another menu, the one of the imported menu first.
     *
     * @return \stdClass|null Menu item or NULL if there's no published one
     */
    public function item( int $id ): ?\stdClass
    {
        return $this->menu->item( $id ) ?? $this->menuItems->get( $id );
    }


    /**
     * Tests if the menu item gets a page, i.e. it's part of the imported menu or a translation with page variant.
     */
    public function paged( \stdClass $menuItem ): bool
    {
        return $this->menu->item( $menuItem->id ) !== null || ( $this->variant !== null && ( $this->variant )( $menuItem->id ) );
    }
}
