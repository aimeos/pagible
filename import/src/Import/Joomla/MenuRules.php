<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Selects the menu items used for a target like the menu rules of Joomla.
 *
 * Menu items in a specific language replace the ones selected before for the same target while the ones for
 * all languages are only used if they are the first ones or the first ones of their layout, e.g. a category
 * blog after a category list. Without language filter, Joomla only uses the menu items for all languages.
 *
 * Like the lookup of Joomla, which stores each menu item for the view with its layout and for the view without
 * layout, a menu item with the default layout is the first one of its layout only if no menu item of any layout
 * was selected for the target before. A menu item with another layout is only compared with the ones of its layout.
 *
 * The rules remember the menu items selected before, so each set of menu items needs its own rules, see fresh().
 */
class MenuRules
{
    /** @var array<string, array<string, true>> Layouts of the selected menu items by target, the empty string for the default layout */
    protected array $used = [];

    /** @var array<string, true> Targets a menu item of any layout was selected for as keys */
    protected array $taken = [];


    /**
     * Initializes the menu rules, use Site::menuRules() to get the ones of the site.
     *
     * @param bool $languageFilter TRUE if the language filter plugin is enabled
     */
    public function __construct( protected bool $languageFilter )
    {
    }


    /**
     * Returns new menu rules with the same settings which remember no selected menu items.
     */
    public function fresh(): self
    {
        return new self( $this->languageFilter );
    }


    /**
     * Returns the target of the menu items of a view with ID.
     *
     * @param string $view View of the menu items, e.g. "category"
     * @param int|string $id ID of the article or category, 0 for lists of the root category or the home menu items
     * @return string Target like "category/5"
     */
    public static function target( string $view, int|string $id ): string
    {
        return $view . '/' . $id;
    }


    /**
     * Returns the target of the menu items of a view without ID and the layout.
     *
     * Each layout of the views without ID is a separate target. The layout is normalised by
     * MenuItem::normalLayout(), so the raw layout of a link can be passed and the default
     * layout is the same target as the view without layout.
     *
     * @param string $view View like "featured"
     * @param string $layout Layout like "blog", "default" or an empty string for the view without layout
     * @return string Target like "featured:blog" or "featured:"
     */
    public static function layoutTarget( string $view, string $layout = '' ): string
    {
        return $view . ':' . MenuItem::normalLayout( $layout );
    }


    /**
     * Tests if the menu item replaces the one selected before for the target.
     *
     * A menu item for all languages is only used if no menu item of its layout was selected before for the
     * target, e.g. a category blog after a category list. A menu item with the default layout is only used if
     * no menu item of any layout was selected before. The targets from layoutTarget() contain the layout,
     * so their menu items never replace the ones of other layouts.
     *
     * @param string $target Target of the menu item from target() or layoutTarget()
     * @param string $layout Layout of the menu item, an empty string for the default layout
     * @return bool TRUE if the menu item is used for the target, FALSE if the one selected before is kept
     */
    protected function accepts( string $target, \stdClass $item, string $layout ): bool
    {
        if( !Row::allLanguages( $item ) ) {
            // without language filter, Joomla only uses the menu items for all languages
            return $this->languageFilter;
        }

        // menu items for all languages don't replace the ones selected before for the same layout, the default layout is taken by any layout
        return $layout === '' ? !isset( $this->taken[$target] ) : !isset( $this->used[$target][$layout] );
    }


    /**
     * Records that a menu item of the layout was selected for the target.
     *
     * @param string $target Target of the menu item from target() or layoutTarget()
     * @param string $layout Layout of the menu item, an empty string for the default layout
     */
    protected function record( string $target, string $layout ): void
    {
        $this->used[$target][$layout] = $this->taken[$target] = true;
    }


    /**
     * Records the menu item for the target if the menu rules accept it.
     *
     * @param string $target Target of the menu item from target() or layoutTarget()
     * @param \stdClass $item Menu item which may replace the one selected before
     * @param string|null $layout Layout of the parsed link of the menu item or NULL to use MenuItem::layout()
     * @return bool TRUE if the menu item is used for the target, FALSE if the one selected before is kept
     */
    public function take( string $target, \stdClass $item, ?string $layout = null ): bool
    {
        $layout ??= MenuItem::layout( $item );

        if( !$this->accepts( $target, $item, $layout ) ) {
            return false;
        }

        $this->record( $target, $layout );
        return true;
    }
}
