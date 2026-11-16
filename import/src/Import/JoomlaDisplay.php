<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * How Joomla shows an article, category or menu item: the menu item showing it, why guests can't see it and the active menu item.
 *
 * The options of the menu items are decoded once per page here, so the classes building the page don't decode them again.
 */
final readonly class JoomlaDisplay
{
    /** @var array<string, mixed> Options of the menu item showing the page, empty if there's none */
    public array $params;

    /** @var array<string, mixed> Options of the menu item Joomla shows the page in, i.e. the own or the active menu item */
    public array $menuParams;


    /**
     * Initializes the object.
     *
     * @param \stdClass|null $item Menu item showing the article or category or NULL if there's none
     * @param string|null $reason Why guests can't see the article or category or NULL if they can
     * @param \stdClass|null $active Menu item of the category Joomla uses if there's no own menu item, NULL otherwise
     */
    public function __construct(
        public ?\stdClass $item,
        public ?string $reason,
        public ?\stdClass $active
    ) {
        $this->params = JoomlaRow::params( $item );
        $this->menuParams = $item ? $this->params : JoomlaRow::params( $active );
    }


    /**
     * Returns how the menu item shows its own page, e.g. a menu heading, link or home page.
     *
     * @param \stdClass $menuItem Menu item of the page
     * @param string|null $reason Why guests can't see the page or NULL if they can
     */
    public static function menu( \stdClass $menuItem, ?string $reason = null ): self
    {
        return new self( $menuItem, $reason, null );
    }


    /**
     * Returns the menu item Joomla shows the page in, the own menu item or the active one of the category.
     *
     * @return \stdClass|null Menu item or NULL if there's none
     */
    public function menuItem(): ?\stdClass
    {
        return $this->item ?? $this->active;
    }


    /**
     * Returns the ID of the menu item Joomla shows the page in.
     *
     * @return int ID of the menu item or 0 if there's none
     */
    public function itemId(): int
    {
        return (int) ( $this->menuItem()->id ?? 0 );
    }
}
