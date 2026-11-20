<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Returns the URLs of the imported Joomla articles, categories, menu items and translations.
 *
 * The URL paths are claimed at once by UrlAssignment, which describes the order the rows claim their paths in.
 * Only the URL paths which differ from the aliases or Joomla routes are kept in the assigned URLs.
 * The object is the single source of the URLs of the imported pages, so it also returns the URLs of the
 * home pages of the languages, which the import uses for the variants of the root page.
 */
class Urls
{
    /**
     * Initializes the object.
     *
     * @param UrlContext $context Domains, routes and menu items the URLs are built from
     * @param AssignedUrls $assigned URL paths assigned by UrlAssignment::assign()
     */
    public function __construct(
        protected UrlContext $context,
        protected AssignedUrls $assigned
    ) {
    }


    /**
     * Returns the Joomla redirects kept because their old URL paths aren't used by the imported pages.
     *
     * @return list<KeptRedirect> Kept redirects in the order of the Joomla redirects
     */
    public function redirects(): array
    {
        return $this->assigned->redirects();
    }


    /**
     * Returns the Joomla redirects skipped because their targets aren't supported, see AssignedUrls::unsupportedRedirects().
     *
     * @return list<KeptRedirect> Skipped redirects in the order of the Joomla redirects
     */
    public function unsupportedRedirects(): array
    {
        return $this->assigned->unsupportedRedirects();
    }


    /**
     * Returns the URL of the page of the article or translation.
     *
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if the article isn't imported
     */
    public function articleUrl( int|string $id ): ?string
    {
        return $this->assigned->home( $id ) ?? $this->keyUrl( $this->assigned->articleKey( $id ) );
    }


    /**
     * Returns the URL path of the category page.
     *
     * The page of the menu item of the imported menu is used even if Joomla uses another menu item for the URL.
     * Hidden categories are found too because only their language is used to select the routes. Translated
     * categories use the URL of their page variant in the domain of their language, the one of the translated menu
     * item if a menu item of the imported menu shows the category they translate. The category of the home menu item and
     * its translations use the home pages of their languages.
     *
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if the category or translation has no page
     */
    public function categoryUrl( int $catId ): ?string
    {
        if( ( $url = $this->assigned->categoryHome( $catId ) ) !== null ) {
            return $url;
        }

        if( $key = $this->assigned->categoryKey( $catId ) ) {
            return $this->keyUrl( $key );
        }

        // translations are only imported as page variants, the pages of the imported language aren't in their language
        if( $this->assigned->categoryTranslation( $catId ) ) {
            return null;
        }

        $item = $this->context->categoryItems->categoryPageItem( $catId ) ?? $this->context->router->categoryRoutes( $catId )->categoryItem( $catId );

        // menu items of the imported menu without own page leave the category to the page of its articles
        if( $item && ( MenuItem::home( $item ) || $this->context->menu->item( $item->id ) !== null ) && ( $url = $this->itemUrl( $item ) ) !== null ) {
            return $url;
        }

        // only categories with imported articles below them get own pages and claim URL paths
        if( !$this->assigned->claimed( Kind::Category->key( $catId ) ) ) {
            return null;
        }

        $category = $this->context->categoryItems->category( $catId );
        return $category ? '/' . $this->categoryPath( $category ) : null;
    }


    /**
     * Returns the URL path of the article page.
     *
     * Articles get their route like categories, so the route Joomla uses is only built for articles which didn't claim
     * a path. Menu items use their path from the menu instead, see menuPath().
     */
    public function articlePath( \stdClass $article ): string
    {
        return $this->assigned->routePath( Kind::Article->key( $article->id ), fn() => RoutePath::of( null, $article ) );
    }


    /**
     * Returns the URL path of the page of a category without own menu item.
     *
     * Categories which weren't claimed because they have no page get the path Joomla uses without unique suffix.
     */
    public function categoryPath( \stdClass $category ): string
    {
        $key = Kind::Category->key( $category->id );
        return $this->assigned->routePath( $key, fn() => $this->context->paths->categoryRoute( $category ) );
    }


    /**
     * Returns the URL path of the page listing the categories.
     *
     * Like the links to the menu items listing the categories, the page of the parent category is used if the menu item isn't imported.
     * Translated categories use the URL of their page variant in the domain of their language like in categoryUrl().
     *
     * @param int $catId ID of the parent category of the list, 0 or 1 for the root category
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if neither the list nor the parent category has a page
     */
    public function listUrl( int $catId ): ?string
    {
        // translated categories only use their page variants, see categoryUrl()
        if( $this->assigned->categoryTranslation( $catId ) ) {
            return $this->categoryUrl( $catId );
        }

        $item = $this->context->router->categoryRoutes( $catId )->listItem( $catId );
        return ( $item ? $this->itemUrl( $item ) : null ) ?? $this->categoryUrl( $catId );
    }


    /**
     * Returns the URL of the page of the menu item of the imported menu or of its variant for a translated menu item.
     *
     * Menu items without own page, e.g. restricted ones or the ones of categories shown by another page, return NULL,
     * so links to them can use the page of their category instead, see categoryUrl().
     *
     * @return string|null URL path, absolute URL for translations in languages with own domains or NULL if the menu item has no own page or variant
     */
    public function menuUrl( \stdClass $item ): ?string
    {
        if( $this->context->categoryItems->hasMenuPage( $item->id ) ) {
            return '/' . $this->menuPath( $item );
        }

        return $this->keyUrl( $this->assigned->menuKey( $item->id ) );
    }


    /**
     * Tests if the translated menu item has a page variant, which menuUrl() returns for menu items without own page.
     *
     * Only the translations of the menu items of the imported menu with own pages claim URL paths for their variants.
     *
     * @param int|string $id ID of the translated menu item, usually one of another menu
     */
    public function menuVariant( int|string $id ): bool
    {
        return $this->assigned->menuKey( $id ) !== null;
    }


    /**
     * Tests if the translated menu item gets a page variant, the same check the translations claimed their URL paths with.
     *
     * @param \stdClass $menuItem Translated menu item, usually one of another menu
     * @see CategoryItems::translationPaged()
     */
    public function menuTranslated( \stdClass $menuItem ): bool
    {
        return $this->context->categoryItems->translationPaged( $menuItem, $this->menuVariant( ... ) );
    }


    /**
     * Returns the URL path of the page of the menu item.
     *
     * Like articlePath() and categoryPath(), menu items which didn't claim a path use the one Joomla uses, which is
     * always their path from the menu, so they need no alias to fall back to.
     */
    public function menuPath( \stdClass $item ): string
    {
        $key = Kind::Menu->key( $item->id );
        return $this->assigned->routePath( $key, fn() => new RoutePath( $this->context->paths->itemPath( $item ), '' ) );
    }


    /**
     * Returns the URL path of the translated article in the domain of its language.
     *
     * The routes of the translated articles depend on their menu items, so they are claimed like the ones of the articles.
     * Unlike categoryTranslationPath() and menuTranslationPath(), there's no fallback: the routes of the translated
     * articles are only known from the stored routes of the articles, see Index::routes(), and every imported translated
     * article claims a URL path except the ones of the article imported into the root page, which use the root page
     * variants instead. So a missing path is an error instead of a route which can't be built here.
     *
     * @throws \LogicException If the translated article didn't claim a URL path
     */
    public function articleTranslationPath( \stdClass $row ): string
    {
        $key = Kind::Article->translationKey( $row->id );
        return $this->assigned->claimedPath( new UrlKey( $key, $this->context->domains->domain( Row::language( $row ) ) ) );
    }


    /**
     * Returns the URL path of the translated category in the domain of its language.
     */
    public function categoryTranslationPath( \stdClass $row ): string
    {
        $key = Kind::Category->translationKey( $row->id );
        return $this->translationPath( $key, fn() => $this->context->paths->categoryTranslationRoute( $row ), $row );
    }


    /**
     * Returns the URL path of the translated menu item in the domain of its language.
     */
    public function menuTranslationPath( \stdClass $row ): string
    {
        $key = Kind::Menu->translationKey( $row->id );
        return $this->translationPath( $key, fn() => $this->context->paths->menuTranslationRoute( $row ), $row );
    }


    /**
     * Returns the URL path of the translated article, category or menu item in the domain of its language.
     *
     * @param Translation $translation Associated row and its type
     * @throws \LogicException If the translated article didn't claim a URL path
     */
    public function translatedPath( Translation $translation ): string
    {
        return match( $translation->kind ) {
            Kind::Article => $this->articleTranslationPath( $translation->row ),
            Kind::Category => $this->categoryTranslationPath( $translation->row ),
            Kind::Menu => $this->menuTranslationPath( $translation->row ),
        };
    }


    /**
     * Returns the URL of the home page of the Joomla language.
     *
     * Kept here, so the links and the import get all URLs from this object.
     *
     * @return string|null URL path, absolute URL for languages with own domains or NULL for unknown languages
     */
    public function homeUrl( string $tag ): ?string
    {
        return $this->context->domains->homeUrl( $tag );
    }


    /**
     * Returns the URL path of the root page variant of the language, which is reserved by UrlAssignment.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the language
     * @return string URL path without leading slash, an empty string for languages on own domains
     */
    public function rootPath( array $language ): string
    {
        return $this->context->domains->rootPath( $language );
    }


    /**
     * Returns the assigned URL path of the translated row, the one Joomla uses if the row didn't claim a path.
     *
     * @param string $key Record key of the translation, e.g. "tc5" or "tm7"
     * @param \Closure(): RoutePath $route Returns the URL path Joomla uses for translations which didn't claim a path
     * @param \stdClass $translation Translated row whose language domain is used
     */
    protected function translationPath( string $key, \Closure $route, \stdClass $translation ): string
    {
        return $this->assigned->routePath( $key, $route, $this->context->domains->domain( Row::language( $translation ) ) );
    }


    /**
     * Returns the URL of the claimed row.
     *
     * @param UrlKey|null $key Record key and language domain of the claimed row or NULL if it didn't claim a path
     * @return string|null URL path, absolute URL in languages with own domains or NULL if there's no key
     */
    protected function keyUrl( ?UrlKey $key ): ?string
    {
        return $key ? $this->context->domains->url( $this->assigned->claimedPath( $key ), $key->domain ) : null;
    }


    /**
     * Returns the URL path of the home page or of the page of the menu item of the imported menu.
     *
     * Home menu items of categories guests can't see have own pages, see CategoryItems::homeCategory().
     * Like Joomla, which links every home menu item to the home page, any other home menu item returns "/"
     * even if the root page doesn't show its article or category, e.g. a home menu item of another menu.
     *
     * @return string|null URL path or NULL if the menu item has no page
     */
    protected function itemUrl( \stdClass $item ): ?string
    {
        return MenuItem::home( $item ) && !$this->context->categoryItems->hasMenuPage( $item->id ) ? '/' : $this->menuUrl( $item );
    }
}
