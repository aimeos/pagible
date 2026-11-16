<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Combines the options of the articles or categories, their menu items and the articles component like Joomla does.
 */
final readonly class Options
{
    /**
     * Option showing the page heading of the menu item.
     *
     * The menus component sets its default if neither the menu item nor the articles component set it, see Site.
     */
    public const PAGE_HEADING_SHOWN = 'show_page_heading';
    /** Value of the options of category blogs which use the option of the article */
    protected const USE_ARTICLE = 'use_article';


    /**
     * Initializes the object.
     *
     * @param array<string, mixed> $defaults Options of the articles component used if the articles, categories and menu items don't override them
     */
    public function __construct( protected array $defaults = [] )
    {
    }


    /**
     * Returns the options of the article or category page.
     *
     * The options of the menu item linking to the article or category and the ones of the articles component are
     * used before the ones of the article. Empty options and options of category blogs set to "use article" use the
     * ones of the article or the articles component like Joomla does.
     *
     * @param array<string, mixed> $own Options of the article, used only where neither the menu item nor the articles
     *  component set them, or an empty array for categories and home pages which have no own options here
     * @param array<string, mixed> $params Options of the menu item linking to the article or category
     * @return array<string, mixed> Options by name
     */
    public function view( array $own, array $params ): array
    {
        return array_replace( Value::filled( $own ), $this->defaults, $this->menu( $params ) );
    }


    /**
     * Returns the options of the article page shown in the menu item of its category.
     *
     * The options of the article are used before the ones of the menu item and of the articles component.
     *
     * @param array<string, mixed> $own Options of the article
     * @param array<string, mixed> $params Options of the menu item of the category
     * @return array<string, mixed> Options by name
     */
    public function inherited( array $own, array $params ): array
    {
        return array_replace( $this->defaults, $this->menu( $params ), Value::filled( $own ) );
    }


    /**
     * Returns the options of the menu item used for the page.
     *
     * Options of category blogs set to "use article" are applied after the ones of the article and the articles component.
     *
     * @param array<string, mixed> $params Options of the menu item
     * @return array<string, mixed> Filled options by name
     */
    protected function menu( array $params ): array
    {
        return array_filter( Value::filled( $params ), fn( $value ) => $value !== self::USE_ARTICLE );
    }
}
