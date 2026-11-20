<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Creates the Joomla routers of the imported language and the languages of the translations.
 *
 * Joomla uses the menu items of all menus guests can see in the language and for all languages
 * and the published categories guests can see to build the URL paths.
 */
class Router
{
    /** Option of the articles component enabling the modern router in Joomla 3 */
    protected const SEF_ADVANCED = 'sef_advanced';
    /** Hidden option of the articles component removing the IDs from the URLs of the legacy router */
    protected const SEF_ADVANCED_LINK = 'sef_advanced_link';
    /** Option of the articles component removing the IDs from the URLs of the modern router */
    protected const SEF_IDS = 'sef_ids';

    /** Builds the URL paths of the articles and categories in the imported language */
    protected Routes $routes;
    /** @var array<string, Routes> Builds the URL paths of the translations by Joomla language tag, created on first use */
    protected array $languageRoutes = [];
    /** Published categories guests can see, which are routed by Joomla, shared by the routes of all languages */
    protected RouteCategories $routeCategories;
    /** @var Collection<int, \stdClass> Menu items of all menus guests can see which Joomla builds the URLs from, see MenuItem::routing() */
    protected Collection $routeItems;
    /** Router options of the site used by the routes of all languages, also if the legacy router of Joomla 3 is used */
    protected RouteOptions $options;


    /**
     * Initializes the object and the router of the imported language.
     *
     * @param Site $site Settings of the Joomla site with the access checks and the plugins, e.g. strict routing of the SEF plugin
     * @param Collection<int, \stdClass> $items Menu items of all menus Joomla builds the URLs from, see MenuItem::routing()
     */
    public function __construct( protected Site $site, Collection $items )
    {
        $access = $site->access;
        $this->routeCategories = new RouteCategories( $site->categories->filter( fn( $row ) => $access->categoryPublic( $row ) ) );
        // Joomla uses the menu items of all menus for the URLs by their position
        $this->routeItems = $items->filter( fn( $item ) => $access->restriction( $item ) === null )->values();
        // Joomla 3 uses the legacy router unless the modern one is enabled, its hidden "sef_advanced_link" option removes the IDs
        $legacy = $site->version->upTo( Version::JOOMLA_3 ) && !Value::enabled( $site->defaults, self::SEF_ADVANCED, false );
        $noIds = Value::enabled( $site->defaults, $legacy ? self::SEF_ADVANCED_LINK : self::SEF_IDS, false );
        // Joomla 5.2+ doesn't use the home menu item for articles without menu item if strict routing is on
        $this->options = new RouteOptions( noIds: $noIds, strict: $site->plugins->strictRouting(), legacy: $legacy );
        $this->routes = $this->build( $this->routeItems->filter( fn( $item ) => $site->onSite( Row::language( $item ) ) ) );
    }


    /**
     * Returns the builder of the URL paths for the articles and categories of the language.
     *
     * Joomla uses the menu items in the language and the ones for all languages.
     *
     * @param string $tag Joomla language tag
     */
    public function languageRoutes( string $tag ): Routes
    {
        return $this->languageRoutes[$tag] ??= $this->build( $this->routeItems->filter( fn( $item ) => Row::inLanguage( $item, $tag ) ) );
    }


    /**
     * Returns the builder of the URL paths for the articles and categories imported into the pages of the imported language.
     *
     * With language filter, Joomla 3 only uses the menu items for all languages for content for all languages.
     *
     * @param string $tag Joomla language tag of the content, e.g. Row::language() of an article
     */
    public function contentRoutes( string $tag ): Routes
    {
        return $this->site->allLanguageItemsOnly() && $tag === Languages::ALL ? $this->languageRoutes( $tag ) : $this->routes;
    }


    /**
     * Returns the builder of the URL paths Joomla uses for the links in the content of the language.
     *
     * Content Joomla shows on the site of the imported language links to the menu items of the imported language,
     * see contentRoutes(), the content of other languages to the menu items of its language like Joomla does on
     * the sites of the other languages.
     *
     * @param string $tag Joomla language tag of the content containing the links or Languages::ALL
     */
    public function linkRoutes( string $tag ): Routes
    {
        return $this->site->onSite( $tag ) ? $this->contentRoutes( $tag ) : $this->languageRoutes( $tag );
    }


    /**
     * Returns the builder of the URL paths Joomla uses for the category in the imported language.
     *
     * Unknown categories like the root category use the routes of the imported language.
     *
     * @param int $catId Category ID, 0 or 1 for the root category
     */
    public function categoryRoutes( int $catId ): Routes
    {
        $category = $this->site->categories->get( $catId );
        return $category ? $this->contentRoutes( Row::language( $category ) ) : $this->routes;
    }


    /**
     * Returns the builder of the URL paths for the visible categories and the menu items.
     *
     * The routes of all languages use the same router, which is chosen once by the constructor and passed in the options.
     *
     * @param Collection<int, \stdClass> $items Menu items Joomla uses for the URL paths
     */
    protected function build( Collection $items ): Routes
    {
        return new Routes( $this->routeCategories, $items, $this->options, $this->site->menuRules() );
    }
}
