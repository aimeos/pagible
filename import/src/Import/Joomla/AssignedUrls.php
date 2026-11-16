<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * URL paths assigned to the imported Joomla menu items, categories, articles, translations and redirects.
 *
 * Returned by UrlAssignment::assign().
 */
final readonly class AssignedUrls
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, UrlKey> $articleKeys Record keys and language domains of the imported articles and translations by ID
     * @param array<int|string, UrlKey> $menuKeys Record keys and language domains of the translated menu items with page variants by ID
     * @param array<int|string, UrlKey> $categoryKeys Record keys and language domains of the page variants of the translated categories by ID,
     *  the ones of the translated menu items for categories shown by a menu item of the imported menu
     * @param array<string, string> $routePaths URL paths Joomla uses or aliases of the claimed rows by record key, e.g. "a5" or "ta7"
     * @param array<string, array<string, string>> $slugs URL paths which differ from the aliases or Joomla routes by language domain and record key
     * @param array<int|string, string> $homes URLs of the home pages used by the article imported into the root page and its translations by ID
     * @param list<KeptRedirect> $redirects Joomla redirects whose old URL paths aren't used by the imported pages
     * @param array<int|string, bool> $translatedCategories IDs of all translated categories as keys, also the ones without page variant
     * @param array<int|string, string> $categoryHomes URLs of the home pages showing the category of the home menu item and its translations by ID
     * @param list<KeptRedirect> $unsupportedRedirects Joomla redirects skipped because their targets aren't supported,
     *  without the ones whose old URL paths are used by the imported pages or kept redirects, also the paths claimed after the redirects
     */
    public function __construct(
        private array $articleKeys = [],
        private array $menuKeys = [],
        private array $categoryKeys = [],
        private array $routePaths = [],
        private array $slugs = [],
        private array $homes = [],
        private array $redirects = [],
        private array $translatedCategories = [],
        private array $categoryHomes = [],
        private array $unsupportedRedirects = []
    ) {
    }


    /**
     * Returns the Joomla redirects whose old URL paths aren't used by the imported pages.
     *
     * @return list<KeptRedirect> Kept redirects in the order of the Joomla redirects
     */
    public function redirects(): array
    {
        return $this->redirects;
    }


    /**
     * Returns the Joomla redirects skipped because their targets aren't supported, see Links::redirectTarget().
     *
     * Redirects whose old URL paths are used by the imported pages, e.g. by the aliases claimed after the redirects, or by
     * kept redirects are skipped silently like the supported ones.
     *
     * @return list<KeptRedirect> Skipped redirects in the order of the Joomla redirects
     */
    public function unsupportedRedirects(): array
    {
        return $this->unsupportedRedirects;
    }


    /**
     * Returns the record key and language domain of the imported article or translation.
     *
     * @return UrlKey|null Key or NULL if the article isn't imported
     */
    public function articleKey( int|string $id ): ?UrlKey
    {
        return $this->articleKeys[$id] ?? null;
    }


    /**
     * Returns the record key and language domain of the page variant showing the translated category.
     *
     * Categories shown by a menu item of the imported menu use its page, so their translations use the page variant
     * of the translated menu item in their language.
     *
     * @return UrlKey|null Key or NULL if the category isn't a translation with page variant
     */
    public function categoryKey( int|string $id ): ?UrlKey
    {
        return $this->categoryKeys[$id] ?? null;
    }


    /**
     * Tests if the category is a translation, which has no page of its own in the imported language.
     */
    public function categoryTranslation( int|string $id ): bool
    {
        return isset( $this->translatedCategories[$id] );
    }


    /**
     * Returns the record key and language domain of the translated menu item with page variant.
     *
     * @return UrlKey|null Key or NULL if the menu item isn't a translation with page variant
     */
    public function menuKey( int|string $id ): ?UrlKey
    {
        return $this->menuKeys[$id] ?? null;
    }


    /**
     * Returns the URL of the home page used by the article imported into the root page or one of its translations.
     *
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if it's no home article
     */
    public function home( int|string $id ): ?string
    {
        return $this->homes[$id] ?? null;
    }


    /**
     * Returns the URL of the home page showing the category of the home menu item or one of its translations.
     *
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if no home page shows the category
     */
    public function categoryHome( int|string $id ): ?string
    {
        return $this->categoryHomes[$id] ?? null;
    }


    /**
     * Tests if the row claimed a URL path, e.g. the categories with own pages.
     *
     * @param string $key Record key of the row, e.g. "c5" for category 5
     */
    public function claimed( string $key ): bool
    {
        return isset( $this->routePaths[$key] );
    }


    /**
     * Returns the URL path assigned to the row.
     *
     * @param string $key Record key of the row, e.g. "a5" for article 5
     * @param string $route URL path Joomla uses or alias of the row used if no other path was assigned
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     */
    public function path( string $key, string $route, string $domain = '' ): string
    {
        return $this->slugs[$domain][$key] ?? $route;
    }


    /**
     * Returns the URL path assigned to the row, the one Joomla uses if the row didn't claim a path.
     *
     * @param string $key Record key of the row, e.g. "c5" or "tm7"
     * @param \Closure(): RoutePath $route Returns the URL path Joomla uses for rows which didn't claim a path
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     */
    public function routePath( string $key, \Closure $route, string $domain = '' ): string
    {
        return $this->path( $key, $this->routePaths[$key] ?? $route()->path(), $domain );
    }


    /**
     * Returns the URL path of the claimed row.
     *
     * @param UrlKey $key Record key and language domain of the claimed row
     * @throws \LogicException If the row didn't claim a URL path
     */
    public function claimedPath( UrlKey $key ): string
    {
        // the URL paths of the claimed rows are always stored together with their record keys
        $route = $this->routePaths[$key->key] ?? throw new \LogicException( "No URL path for \"{$key->key}\"" );
        return $this->path( $key->key, $route, $key->domain );
    }
}
