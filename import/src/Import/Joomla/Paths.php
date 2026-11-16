<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

/**
 * Builds the URL paths Joomla uses for the articles, categories and menu items.
 *
 * Like the Joomla router, the URL paths of the menu items linking to the articles and categories are used
 * before the ones built from the menu items of their parent categories. The URL paths aren't unique yet,
 * UrlAssignment claims the ones used by the imported pages, see there for the order.
 */
class Paths
{
    /**
     * Initializes the object.
     *
     * @param Router $router Builds the URL paths of the articles and categories like Joomla
     * @param CategoryItems $categoryItems Returns the menu items of other menus showing the categories
     */
    public function __construct( protected Router $router, protected CategoryItems $categoryItems )
    {
    }


    /**
     * Returns the URL path of the menu item including the aliases of its parent items.
     *
     * Home menu items have no URL path, so their alias is used for the pages of their translations.
     */
    public function itemPath( \stdClass $item ): string
    {
        return MenuItem::home( $item ) ? Row::aliasOrSlug( $item ) : MenuItem::path( $item );
    }


    /**
     * Returns the URL path of the article like the Joomla router builds it.
     *
     * Joomla uses the URL path of the menu item linking to the article if there's one. Articles without own
     * menu item use the path of the menu item of their category or its parents followed by the segments of
     * the categories below and the article. The home page has no path, so its article gets the path it would
     * have without the home menu item.
     *
     * @param \stdClass|null $item Menu item Joomla uses for the article or NULL if there's none
     * @return RoutePath URL path or NULL if Joomla has no menu item for the article and its categories and the alias
     */
    public function articleRoute( \stdClass $article, ?\stdClass $item = null ): RoutePath
    {
        return $this->languageArticleRoute( $article, $item, $this->router->contentRoutes( Row::language( $article ) ) );
    }


    /**
     * Returns the URL path of the category of the imported language like the Joomla router builds it.
     *
     * The home menu item of a category isn't used, the path is built from the menu items of the parent categories instead.
     * The menu items of the imported menu aren't used because the category page has their URL path, the path of a menu
     * item of the category in another menu is returned then.
     *
     * @return RoutePath URL path or NULL if Joomla has no menu item for the category and its parents and the alias
     */
    public function categoryRoute( \stdClass $category ): RoutePath
    {
        $item = $this->categoryItems->otherItem( $category );
        $routes = $this->router->contentRoutes( Row::language( $category ) );

        return RoutePath::of( $item ? $this->itemPath( $item ) : $routes->category( (int) $category->id ), $category );
    }


    /**
     * Returns the URL path of the translated article like the Joomla router builds it for the language of the translation.
     *
     * Joomla uses the menu items in the language of the translation and the ones for all languages.
     *
     * @param \stdClass $row Translated article
     * @param \stdClass|null $item Menu item Joomla uses for the translated article or NULL if there's none
     */
    public function articleTranslationRoute( \stdClass $row, ?\stdClass $item = null ): RoutePath
    {
        return $this->languageArticleRoute( $row, $item, $this->router->languageRoutes( Row::language( $row ) ) );
    }


    /**
     * Returns the URL path of the translated category like the Joomla router builds it for the language of the translation.
     *
     * Translated categories use the own menu item of their language, the home menu item isn't used.
     *
     * @param \stdClass $row Translated category
     */
    public function categoryTranslationRoute( \stdClass $row ): RoutePath
    {
        $routes = $this->router->languageRoutes( Row::language( $row ) );
        $catId = (int) $row->id;
        $item = $routes->ownCategoryItem( $catId );

        return RoutePath::of( $item ? $this->itemPath( $item ) : $routes->category( $catId ), $row );
    }


    /**
     * Returns the URL path of the translated menu item, menu items have their own URL paths.
     *
     * @param \stdClass $row Translated menu item
     */
    public function menuTranslationRoute( \stdClass $row ): RoutePath
    {
        return RoutePath::of( $this->itemPath( $row ), $row );
    }


    /**
     * Returns the URL path of the article built from the routes of its language.
     *
     * @param \stdClass|null $item Menu item Joomla uses for the article or NULL if there's none
     * @param Routes $routes Routes of the language of the article
     */
    protected function languageArticleRoute( \stdClass $article, ?\stdClass $item, Routes $routes ): RoutePath
    {
        $own = MenuItem::withoutHome( $item );
        return RoutePath::of( $own ? $this->itemPath( $own ) : $routes->article( $article ), $article );
    }
}
