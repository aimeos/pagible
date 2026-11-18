<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Resolves the Joomla links, menu items and redirects to the URL paths of the imported pages.
 *
 * Like Joomla, links to menu items showing the same page as the link use the URL path of the menu item.
 */
class Links
{
    /** Schemes of the URLs kept as they are, e.g. the targets of URL menu items imported as link pages */
    protected const SUPPORTED_SCHEMES = 'https?://|mailto:|tel:';
    /** URLs with a supported scheme */
    protected const SUPPORTED_SCHEME_PATTERN = '#^(?:' . self::SUPPORTED_SCHEMES . ')#i';
    /** URLs with another scheme, e.g. "javascript:" */
    protected const UNSUPPORTED_SCHEME_PATTERN = '#^(?!' . self::SUPPORTED_SCHEMES . ')[a-z][a-z0-9+.-]*:#i';

    /** Query parameter of the Joomla links containing the language, ignored when comparing the links with the menu items */
    protected const LANG_PARAM = 'lang';
    /** Query parameter of the Joomla links containing the view, e.g. "article" */
    protected const VIEW_PARAM = 'view';
    /** Query parameter of the Joomla links containing the ID of the article or category */
    protected const ID_PARAM = 'id';
    /** Query parameter of the Joomla links containing the layout of the view, e.g. "blog" */
    protected const LAYOUT_PARAM = 'layout';
    /** Option of the alias menu items containing the ID of the menu item they point to */
    protected const ALIAS_OPTION = 'aliasoptions';
    /** Query parameters Joomla casts to integers, so "05" and "5abc" are the same as "5" */
    protected const ID_PARAMS = [self::ID_PARAM => true, 'catid' => true, MenuItem::ITEM_ID => true];
    /** Pseudo view of the links without query parameters, which show the home page */
    protected const VIEW_HOME = '';

    /** @var array<int, true> IDs of the menu items whose targets are resolved, URL menu items can link to each other */
    protected array $resolving = [];
    /** @var \WeakMap<\stdClass, bool> Results of menuLinkable() by URL or alias menu item */
    protected \WeakMap $menuLinkable;
    /** @var \WeakMap<\stdClass, bool> Results of targetLinkable() by target menu item */
    protected \WeakMap $targetLinkable;


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site
     * @param Collection<int|string, \stdClass> $menuItems Published menu items of all menus by ID for the links to the ones of other menus
     * @param Urls $urls Returns the URL paths of the imported pages
     * @param Router $router Joomla routes of the imported language and the languages of the translations
     * @param Menu $menu Menu items of the imported menu
     * @param Origin $origin Recognizes the URLs of the Joomla site
     */
    public function __construct(
        protected Site $site,
        protected Collection $menuItems,
        protected Urls $urls,
        protected Router $router,
        protected Menu $menu,
        protected Origin $origin
    ) {
        $this->menuLinkable = new \WeakMap();
        $this->targetLinkable = new \WeakMap();
    }


    /**
     * Returns the URL path of the page for the query parameters of a Joomla link.
     *
     * Supported are links to articles, categories, category lists, featured articles, the archive and menu items
     * like "index.php?option=com_content&view=article&id=5",
     * "index.php?Itemid=3" or "index.php?option=com_content&view=featured&Itemid=3" if the menu item shows the same page.
     * Like Joomla, links to the views without ID use the menu items of the language of the content containing them
     * and links without query parameters like "index.php?" show the home page of that language. Links with the language
     * only like "index.php?lang=de", e.g. of the language switcher, show the home page of that language, see langTag().
     *
     * @param array<array-key, mixed> $params Query parameters of the link
     * @param string $tag Joomla language tag of the content containing the link, e.g. of a translated article, or Languages::ALL
     * @return string|null URL path or NULL if the page isn't imported or unknown
     */
    public function resolve( array $params, string $tag ): ?string
    {
        $id = Uri::id( $params[self::ID_PARAM] ?? null );

        // like the language filter, the language of the link replaces the one of the content, unknown languages don't
        if( isset( $params[self::LANG_PARAM] ) && array_diff_key( $params, [self::LANG_PARAM => true] ) === [] )
        {
            $tag = $this->langTag( Value::string( $params[self::LANG_PARAM] ) ) ?? $tag;
            $params = [];
        }

        // the same decisions as queryLinkable(), so the link pages and the resolved links can't diverge
        return match( self::directView( $params ) ) {
            // like Joomla, the script without query parameters shows the home page, the one of the language with language filter
            self::VIEW_HOME => ( $this->site->onSite( $tag ) ? null : $this->urls->homeUrl( $tag ) ) ?? '/',
            MenuItem::VIEW_CATEGORIES => $this->urls->listUrl( $id ),
            MenuItem::VIEW_ARTICLE => $this->urls->articleUrl( $id ),
            MenuItem::VIEW_CATEGORY => $this->urls->categoryUrl( $id ),
            default => ( $itemId = self::queryItemId( $params, $tag, $this->menu, $this->menuItems, $this->router ) ) > 0 ? $this->byItemId( $itemId ) : null,
        };
    }


    /**
     * Returns the view of the links which point to a page directly instead of a menu item.
     *
     * Links without query parameters show the home page, links to articles and categories with ID and the
     * lists of categories use the pages of the articles and categories, see resolve().
     *
     * @param array<array-key, mixed> $params Query parameters of the link
     * @return string|null VIEW_HOME, the categories, article or category view or NULL if the link needs a menu item
     */
    protected static function directView( array $params ): ?string
    {
        $id = Uri::id( $params[self::ID_PARAM] ?? null );
        $view = MenuItem::queryView( $params );

        return match( true ) {
            $params === [] => self::VIEW_HOME,
            $view === MenuItem::VIEW_CATEGORIES => $view,
            $id > 0 && in_array( $view, [MenuItem::VIEW_ARTICLE, MenuItem::VIEW_CATEGORY], true ) => $view,
            default => null,
        };
    }


    /**
     * Returns the ID of the menu item whose page the link without direct target uses, see directView().
     *
     * Like Joomla, links to the views without ID like the featured articles use the menu item showing the view in the
     * language of the content containing them, see Router::linkRoutes(). Other links need the ID of a menu item, and
     * links with view or ID only use it if the menu item shows the same like Joomla uses its URL path then.
     *
     * @param array<array-key, mixed> $params Query parameters of the link
     * @param string $tag Joomla language tag of the content containing the link
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @return int ID of the menu item or 0 if there's none, it shows another page or the ID is invalid
     */
    protected static function queryItemId( array $params, string $tag, Menu $menu, Collection $menuItems, Router $router ): int
    {
        $view = MenuItem::queryView( $params );

        if( !isset( $params[MenuItem::ITEM_ID] ) && in_array( $view, MenuItem::VIEWS_WITHOUT_ID, true ) )
        {
            $item = $router->linkRoutes( $tag )->viewItem( (string) $view, Value::string( $params[self::LAYOUT_PARAM] ?? null ) );
            return $item ? (int) $item->id : 0;
        }

        // like Joomla, the menu item ID is the integer at the start
        if( ( $itemId = Uri::id( $params[MenuItem::ITEM_ID] ?? null ) ) <= 0 ) {
            return 0;
        }

        if( ( isset( $params[self::VIEW_PARAM] ) || isset( $params[self::ID_PARAM] ) )
            && !self::sameQuery( $params, self::findItem( $itemId, $menu, $menuItems ) )
        ) {
            return 0;
        }

        return $itemId;
    }


    /**
     * Returns the Joomla language tag of the language parameter of a link like "index.php?lang=de".
     *
     * The language filter uses the URL codes of the content languages, which are the language codes of the
     * imported languages, see Languages::language(), so "de" is "de-DE". Like Joomla, the language tags
     * themselves like "de-DE" are accepted too and the codes are compared case-insensitively.
     *
     * @param string $code URL code or language tag of the language, e.g. "de" or "de-DE"
     * @return string|null Joomla language tag of a usable content language or NULL if it's unknown
     */
    protected function langTag( string $code ): ?string
    {
        $code = strtolower( trim( $code ) );
        $languages = $this->site->languages;

        if( $code === '' ) {
            return null;
        }

        foreach( $languages->tags() as $tag )
        {
            if( strtolower( $languages->language( $tag )['lang'] ?? '' ) === $code ) {
                return $tag;
            }
        }

        foreach( $languages->tags() as $tag )
        {
            if( strtolower( trim( $tag ) ) === $code ) {
                return $tag;
            }
        }

        return null;
    }


    /**
     * Replaces the links to Joomla pages in the HTML by the URL paths of the imported pages like the "System - SEF" plugin does for the whole page.
     *
     * Absolute links to the Joomla site are replaced too. The plugin also makes the relative URLs absolute if it's enabled.
     *
     * @param string $html HTML with links, e.g. of an article text
     * @param string $tag Joomla language tag of the content containing the links or Languages::ALL
     * @return string HTML with replaced links, unknown links are kept
     */
    public function replace( string $html, string $tag ): string
    {
        $html = HtmlUrls::links( $html, fn( array $params ) => $this->resolve( $params, $tag ), $this->origin->sites() );
        return $this->site->plugins->sef() ? HtmlUrls::absolute( $html ) : $html;
    }


    /**
     * Tests if the target of the URL or alias menu item can be supported, before the URLs of the pages are known.
     *
     * CategoryItems selects the link pages by this check, so only the URL and alias menu items which
     * menuItemLink() can resolve claim URL paths. URL menu items with anchors, without link or with other
     * schemes like "javascript:" aren't supported. Internal links like "index.php?option=com_content&view=featured"
     * are tested like resolve() handles them, see queryLinkable(). Alias menu items must point to another known
     * menu item, which is tested like byMenuItem() resolves it, see targetLinkable(). Aliases and URLs pointing
     * back to a menu item of the chain aren't supported. Whether the article or category of the target is imported
     * isn't known yet, so these targets can still be unresolved by menuItemLink().
     *
     * @param \stdClass $menuItem URL or alias menu item
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param (\Closure(int|string): bool)|null $variant Tests if the translated menu item of another menu has a page variant
     *  because it translates a menu item of the imported menu with own page, see Urls::menuVariant(), or NULL if unknown
     * @param array<int|string, true> $seen IDs of the URL and alias menu items of the chain already tested as keys
     */
    public static function linkable( \stdClass $menuItem, Menu $menu, Collection $menuItems, Router $router,
        ?\Closure $variant = null, array $seen = [] ): bool
    {
        $seen[$menuItem->id ?? 0] = true;

        if( MenuItem::type( $menuItem ) === MenuItem::TYPE_ALIAS )
        {
            return self::itemLinkable( self::aliasId( $menuItem ), $menu, $menuItems, $router, $variant, $seen );
        }

        $link = trim( (string) ( $menuItem->link ?? '' ) );

        // Joomla URLs with query string like "index.php?option=com_content&view=article&id=5", see urlTarget()
        if( ( $query = self::indexQuery( $link ) ) !== null )
        {
            $params = Uri::query( $query[0] );
            return self::queryLinkable( $params, Row::language( $menuItem ), $menu, $menuItems, $router, $variant, $seen );
        }

        return self::supportedUrl( $link );
    }


    /**
     * Returns the query string and the fragment of a Joomla URL routed by the script.
     *
     * Joomla routes the URLs with the script name and a query string like "index.php?option=com_content&view=article&id=5"
     * and relative URLs with a query string only like "?Itemid=3" or "/?Itemid=3", which are relative to the script,
     * the same way. The script without query string like "index.php" has an empty one, see Origin::indexUrlFragment().
     *
     * @param string $url Trimmed URL path, e.g. "index.php?Itemid=3#top", "/index.php?", "index.php", "?Itemid=3" or "/?Itemid=3"
     * @return array{0: string, 1: string}|null Query string without "?" and the fragment with "#" or an empty string if there's none,
     *  NULL if it's no Joomla URL routed by the script
     */
    protected static function indexQuery( string $url ): ?array
    {
        if( !preg_match( '#^' . Origin::indexUrlFragment() . '([^\#]*)(\#.*)?$#is', $url, $match ) ) {
            return null;
        }

        return [$match[1], $match[2] ?? ''];
    }


    /**
     * Tests if the URL without Joomla query string can be a link target, see linkable() and urlTarget().
     *
     * Empty URLs, anchors only like "#top" and URLs with other schemes than SUPPORTED_SCHEMES like "javascript:" or
     * "ftp:" aren't supported, URL paths and URLs of other sites with a supported scheme or without scheme are.
     *
     * @param string $url Trimmed URL, e.g. "https://example.com/", "new-page" or "//example.com/page"
     */
    protected static function supportedUrl( string $url ): bool
    {
        return $url !== '' && !str_starts_with( $url, '#' ) && !preg_match( self::UNSUPPORTED_SCHEME_PATTERN, $url );
    }


    /**
     * Returns the target of the URL or alias menu item.
     *
     * URL menu items must link to an absolute URL, an e-mail address, a phone number or an URL path.
     * Their links are resolved in the language of the menu item. Menu items whose targets aren't supported
     * by linkable() have no target, the same ones get no link pages. Translated menu items of other menus can also
     * point to the page variants of other translated menu items, the menu items of the imported menu can't because
     * CategoryItems selects their pages before the variants are known.
     *
     * @return string|null Target or NULL if it's not supported
     */
    public function menuItemLink( \stdClass $menuItem ): ?string
    {
        // the whole chain of the menu item is validated only once, also if it's part of the chains of other menu items
        $linkable = $this->menuLinkable[$menuItem] ??= self::menuLinkable( $menuItem, $this->menu, $this->menuItems,
            $this->router, $this->urls->menuVariant( ... ) );

        if( !$linkable ) {
            return null;
        }

        if( MenuItem::type( $menuItem ) === MenuItem::TYPE_ALIAS ) {
            return $this->byAlias( $menuItem );
        }

        return $this->urlTarget( (string) ( $menuItem->link ?? '' ), Row::language( $menuItem ) );
    }


    /**
     * Tests if linkable() supports the target of the URL or alias menu item, also pointing to page variants if it's allowed to.
     *
     * Only menu items which aren't part of the imported menu can point to the page variants of translated menu items,
     * the ones of the imported menu can't because CategoryItems selects their pages before the variants are known.
     * The same check selects the translations with page variants, see CategoryItems::translationPaged(), and
     * resolves the targets, see menuItemLink(), so both can't diverge.
     *
     * @param \stdClass $menuItem URL or alias menu item
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param \Closure(int|string): bool $variant Tests if the translated menu item of another menu has a page variant
     */
    public static function menuLinkable( \stdClass $menuItem, Menu $menu, Collection $menuItems, Router $router,
        \Closure $variant ): bool
    {
        $variant = $menu->item( $menuItem->id ?? 0 ) === null ? $variant : null;
        return self::linkable( $menuItem, $menu, $menuItems, $router, $variant );
    }


    /**
     * Tests if the translated menu item gets a page variant like its URL path was claimed for.
     *
     * Only forwards to Urls::menuTranslated() because Pages gets the links but not the URLs, which
     * are only used through the links there, so the check isn't passed to Pages separately.
     *
     * @param \stdClass $menuItem Translated menu item, usually one of another menu
     * @see CategoryItems::translationPaged()
     */
    public function menuTranslated( \stdClass $menuItem ): bool
    {
        return $this->urls->menuTranslated( $menuItem );
    }


    /**
     * Returns the link target of a Joomla URL, e.g. the new URL of a redirect or the URL of a menu item.
     *
     * Joomla routes internal links and the SEF plugin makes relative links relative to the site. Absolute
     * URLs of the Joomla site are handled like URL paths. Like linkable(), empty URLs, anchors only and URLs with
     * unsupported schemes like "javascript:" or "ftp:" have no target, see supportedUrl(). The fragments of the
     * Joomla URLs are kept like the browser keeps them when following the routed URL, e.g. "/about#top".
     *
     * @param string $url URL like "https://example.com/", "new-page" or "index.php?option=com_content&view=article&id=5"
     * @param string $tag Joomla language tag of the menu item or content containing the URL or Languages::ALL
     * @param string $dir Directory relative URL paths are relative to without leading and trailing slash
     * @return string|null Link target or NULL if the URL isn't supported or the Joomla page isn't imported
     */
    public function urlTarget( string $url, string $tag, string $dir = '' ): ?string
    {
        $url = trim( $url );
        $local = $this->origin->local( $url ) ?? $url;

        // like browsers, relative URL paths and dot segments are resolved first, e.g. "../index.php?Itemid=3"
        if( self::pathLike( $local ) ) {
            $local = $this->resolvePath( $local, $dir );
        }

        // Joomla URLs with query string like "index.php?option=com_content&view=article&id=5" or "?Itemid=3"
        if( ( $query = self::indexQuery( $local ) ) !== null )
        {
            $target = $this->resolve( Uri::query( $query[0] ), $tag );
            return $target !== null ? $target . $query[1] : null;
        }

        if( !self::supportedUrl( $local ) ) {
            return null;
        }

        if( preg_match( self::SUPPORTED_SCHEME_PATTERN, $local ) ) {
            return $local;
        }

        // URLs of other sites without scheme use the one of the page, which is HTTPS nowadays
        if( str_starts_with( $local, '//' ) ) {
            return Uri::schemed( $local );
        }

        return $this->pathTarget( $local );
    }


    /**
     * Returns the link target of the kept Joomla redirect.
     *
     * Relative new URLs are relative to the directory of the old URL like browsers resolve them, see KeptRedirect::$dir,
     * and because Joomla redirect rows have no language, their targets are resolved for all languages. Only Joomla URLs
     * like "index.php?option=com_content&view=article&id=5" are resolved to the imported pages, URL paths and URLs of other
     * sites are kept without checking them because they can point to pages which aren't part of the Joomla site.
     * The same check decides which redirects claim their old URL paths, see UrlAssignment, so only imported
     * redirects reserve them.
     *
     * @return string|null Target URL or NULL if the target isn't supported or the Joomla URL points to a page which isn't imported
     */
    public function redirectTarget( KeptRedirect $redirect ): ?string
    {
        return $this->urlTarget( $redirect->url, Languages::ALL, $redirect->dir );
    }


    /**
     * Returns the URL path of the page an alias menu item points to.
     *
     * Aliases pointing to themselves are ruled out by linkable() before.
     *
     * @return string|null URL path or NULL if the target isn't supported or not imported
     */
    protected function byAlias( \stdClass $menuItem ): ?string
    {
        return $this->byItemId( self::aliasId( $menuItem ) );
    }


    /**
     * Returns the ID of the menu item the alias menu item points to.
     *
     * @return int ID of the menu item or 0 if the option isn't an ID
     */
    protected static function aliasId( \stdClass $menuItem ): int
    {
        return Uri::id( Row::params( $menuItem )[self::ALIAS_OPTION] ?? null );
    }


    /**
     * Returns the URL path of the page of the menu item with the ID, the one of the imported menu or another menu.
     *
     * URL menu items linking to themselves directly or by other URL menu items have no target.
     *
     * @return string|null URL path or NULL if the menu item isn't supported, not imported or links to itself
     */
    protected function byItemId( int $id ): ?string
    {
        if( isset( $this->resolving[$id] ) || !( $target = $this->menuItem( $id ) ) ) {
            return null;
        }

        $this->resolving[$id] = true;

        try {
            return $this->byMenuItem( $target );
        } finally {
            unset( $this->resolving[$id] );
        }
    }


    /**
     * Returns the URL path of the page of the menu item.
     *
     * Supported are the home pages, articles, URLs, the pages of the imported menu items and the pages
     * of the categories menu items of other menus show or list the subcategories of. Targets targetLinkable() doesn't
     * support, e.g. alias menu items with unsupported targets, have no URL path. Like Joomla, which links every home
     * menu item to the home page, home menu items without own page return "/" even if the root page doesn't show their
     * article or category, e.g. home menu items of other menus or ones of unknown languages.
     *
     * @return string|null URL path or NULL if the menu item isn't supported or not imported
     */
    protected function byMenuItem( \stdClass $target ): ?string
    {
        // the same check selects the link pages and the translations claiming URL paths, so both can't diverge;
        // it validates the chain of the target on its own with a fresh set of seen menu items, independent of the
        // menu items currently resolved, which byItemId() stops if they link back. Pages of other menu items can always
        // be page variants here, URL targets are restricted again by menuItemLink() if they are part of the imported menu
        $linkable = $this->targetLinkable[$target] ??= self::targetLinkable( $target, $this->menu, $this->menuItems,
            $this->router, $this->urls->menuVariant( ... ), [] );

        if( !$linkable ) {
            return null;
        }

        // with language filter, the home menu items of the other languages are the home pages of their languages
        if( MenuItem::home( $target ) )
        {
            $tag = Row::language( $target );
            $other = !$this->site->onSite( $tag );

            // home menu items of categories guests can't see have own pages, unknown languages use the home page of the imported language
            return ( $other ? $this->urls->homeUrl( $tag ) : $this->urls->menuUrl( $target ) ) ?? '/';
        }

        // only imported articles and their translations have pages
        $link = MenuLink::of( $target );

        if( $articleId = $link->article() ) {
            return $this->urls->articleUrl( $articleId );
        }

        // URL menu items with a link, the ones without link or with "#" are folders handled below like headings
        if( $link->kind === MenuLink::KIND_LINK && MenuItem::type( $target ) === MenuItem::TYPE_URL ) {
            return $this->menuItemLink( $target );
        }

        // only the folders, categories and link pages of the imported menu and their translations have own pages
        if( ( $url = $this->urls->menuUrl( $target ) ) !== null ) {
            return $url;
        }

        // categories of other menu items are shown by the pages of their categories like the ones listing their subcategories
        $catId = $link->category();
        return $catId ? $this->urls->categoryUrl( $catId ) : null;
    }


    /**
     * Tests if the URL is a URL path with or without leading slash, not a query string or fragment only or a URL with scheme or host.
     *
     * @param string $url Trimmed URL, e.g. "page", "../index.php?Itemid=3", "/page", "?Itemid=3" or "https://example.com/"
     */
    protected static function pathLike( string $url ): bool
    {
        return $url !== '' && !preg_match( '#^(?:[?\#]|//|[a-z][a-z0-9+.-]*:)#i', $url );
    }


    /**
     * Returns the URL path relative to the root of the Joomla site like browsers resolve the relative URL paths.
     *
     * Relative URL paths are relative to the directory, but Joomla routes the URLs starting with the script name
     * like "index.php/page" or "index.php?Itemid=3", so they aren't. The dot segments like "../page" are removed,
     * see dotless(), so "../../index.php/page" in the directory "a/b" is "/index.php/page". The query string and
     * the fragment are kept.
     *
     * @param string $local URL path with or without leading slash and optional query and fragment, e.g. "../page.html?x=1#top"
     * @param string $dir Directory relative URL paths are relative to without leading and trailing slash
     * @return string URL path with leading slash, e.g. "/a/page.html?x=1#top"
     */
    protected function resolvePath( string $local, string $dir ): string
    {
        [$raw, $rest] = Uri::split( $local );

        // Joomla routes URLs starting with the script name, so they aren't relative to the directory
        $relative = !str_starts_with( $raw, '/' ) && $this->origin->unscripted( $raw ) === $raw;
        $prefix = $dir !== '' && $relative ? $dir . '/' : '';

        return '/' . self::dotless( $prefix . ltrim( $raw, '/' ) ) . $rest;
    }


    /**
     * Returns the link target of a URL path of the Joomla site.
     *
     * The script name and the suffix are removed by Origin::targetPath(), the query string and the fragment are kept.
     *
     * @param string $local URL path relative to the root without dot segments, see resolvePath(), e.g. "/index.php/page.html?x=1#top"
     * @return string URL path with leading slash, e.g. "/page?x=1#top"
     */
    protected function pathTarget( string $local ): string
    {
        [$path, $rest] = Uri::split( $local );
        return '/' . $this->origin->targetPath( ltrim( $path, '/' ) ) . $rest;
    }


    /**
     * Removes the dot segments from the URL path like browsers do, see "remove_dot_segments" of RFC 3986.
     *
     * Segments like "a/b/../c" are resolved to "a/c", parent segments above the root are dropped. Paths ending
     * in a dot segment like "a/b/.." are directories, so they end with a slash, e.g. "a/".
     *
     * @param string $path URL path without leading slash, query and fragment, e.g. "a/b/../up"
     * @return string URL path without dot segments and leading slash, e.g. "a/up"
     */
    protected static function dotless( string $path ): string
    {
        $segments = [];
        $parts = explode( '/', $path );

        foreach( $parts as $segment )
        {
            if( $segment === '..' ) {
                array_pop( $segments );
            } elseif( $segment !== '.' ) {
                $segments[] = $segment;
            }
        }

        if( in_array( end( $parts ), ['.', '..'], true ) ) {
            $segments[] = '';
        }

        return implode( '/', $segments );
    }


    /**
     * Tests if the menu item links to the same page as the query parameters.
     *
     * Like Joomla, IDs like "5:about" or "05" are the same as their integer value, see queryValue().
     *
     * @param array<array-key, mixed> $params Query parameters of the link
     * @param \stdClass|null $item Menu item of the link or NULL if it's unknown
     */
    protected static function sameQuery( array $params, ?\stdClass $item ): bool
    {
        $query = MenuItem::query( $item );

        foreach( array_diff_key( $params, [MenuItem::ITEM_ID => true, self::LANG_PARAM => true] ) as $name => $value )
        {
            if( self::queryValue( $name, $query[$name] ?? null ) !== self::queryValue( $name, $value ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Returns the query parameter value without the alias of IDs like "5:about".
     *
     * Joomla casts the ID parameters to integers, so "5:about", "05" and "5" are the same. Other values with
     * alias like "5:about" are compared by their numeric part.
     *
     * @param int|string $name Query parameter name
     * @param mixed $value Query parameter value
     * @return mixed Integer of ID parameters, numeric part of other values with alias as string, other values as they are
     */
    protected static function queryValue( int|string $name, mixed $value ): mixed
    {
        if( isset( self::ID_PARAMS[$name] ) && ( is_string( $value ) || is_int( $value ) ) ) {
            return Uri::id( $value );
        }

        return is_string( $value ) && preg_match( '/^(\d+):/', $value, $match ) ? $match[1] : $value;
    }


    /**
     * Tests if resolve() can resolve the query parameters of an internal link, before the URLs of the pages are known.
     *
     * Like resolve(), links without query parameters to the home page and links to articles, categories and category
     * lists are supported, whether their pages are imported isn't known yet, see directView(). Links to views without ID
     * like the featured articles need the menu item Joomla uses for the view, other links like the ones of other components
     * need a known menu item showing the same, see queryItemId(). Unlike resolve(), links with the language only like
     * "index.php?lang=de" aren't supported because the languages of the language filter aren't passed.
     *
     * @param array<array-key, mixed> $params Query parameters of the link
     * @param string $tag Joomla language tag of the menu item containing the link
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param (\Closure(int|string): bool)|null $variant Tests if the translated menu item has a page variant or NULL if unknown
     * @param array<int|string, true> $seen IDs of the URL and alias menu items of the chain already tested as keys
     */
    protected static function queryLinkable( array $params, string $tag, Menu $menu, Collection $menuItems, Router $router,
        ?\Closure $variant, array $seen ): bool
    {
        if( self::directView( $params ) !== null ) {
            return true;
        }

        $itemId = self::queryItemId( $params, $tag, $menu, $menuItems, $router );
        return $itemId > 0 && self::itemLinkable( $itemId, $menu, $menuItems, $router, $variant, $seen );
    }


    /**
     * Tests if byItemId() can resolve the menu item with the ID, before the URLs of the pages are known.
     *
     * @param int $id ID of the menu item of the imported menu or another menu
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param (\Closure(int|string): bool)|null $variant Tests if the translated menu item has a page variant or NULL if unknown
     * @param array<int|string, true> $seen IDs of the URL and alias menu items of the chain already tested as keys
     */
    protected static function itemLinkable( int $id, Menu $menu, Collection $menuItems, Router $router,
        ?\Closure $variant, array $seen ): bool
    {
        if( isset( $seen[$id] ) || !( $target = self::findItem( $id, $menu, $menuItems ) ) ) {
            return false;
        }

        return self::targetLinkable( $target, $menu, $menuItems, $router, $variant, $seen );
    }


    /**
     * Tests if byMenuItem() can resolve the target menu item, before the URLs of the pages are known.
     *
     * Home pages, articles and categories are supported, URL menu items are tested themselves. Folders, lists of
     * all categories and alias menu items only have own pages if they are part of the imported menu, alias menu
     * items only if they are linkable themselves. Like Urls::menuUrl(), the translations of these menu items
     * use their page variants if they have ones. URL menu items without link or with "#" are folders.
     *
     * @param \stdClass $target Menu item of the imported menu or another menu
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @param Router $router Joomla routes returning the menu items of the links to views without ID
     * @param (\Closure(int|string): bool)|null $variant Tests if the translated menu item has a page variant or NULL if unknown
     * @param array<int|string, true> $seen IDs of the URL and alias menu items of the chain already tested as keys
     */
    protected static function targetLinkable( \stdClass $target, Menu $menu, Collection $menuItems, Router $router,
        ?\Closure $variant, array $seen ): bool
    {
        $link = MenuLink::of( $target );
        $paged = $menu->item( $target->id ) !== null || ( $variant !== null && $variant( $target->id ) );

        return match( true ) {
            MenuItem::home( $target ), $link->article() !== null => true,
            $link->kind === MenuLink::KIND_FOLDER => $paged,
            MenuItem::type( $target ) === MenuItem::TYPE_URL => self::linkable( $target, $menu, $menuItems, $router, $variant, $seen ),
            $link->kind === MenuLink::KIND_LINK => $paged && self::linkable( $target, $menu, $menuItems, $router, $variant, $seen ),
            $link->kind === MenuLink::KIND_CATEGORIES && $link->id === null => $paged,
            default => $link->category() !== null,
        };
    }


    /**
     * Returns the published menu item of the imported menu or another menu.
     *
     * @return \stdClass|null Menu item or NULL if there's no published one
     */
    protected function menuItem( int $id ): ?\stdClass
    {
        return self::findItem( $id, $this->menu, $this->menuItems );
    }


    /**
     * Returns the published menu item of the imported menu or another menu, the one of the imported menu first.
     *
     * @param int $id ID of the menu item
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $menuItems Published menu items of all menus by ID
     * @return \stdClass|null Menu item or NULL if there's no published one
     */
    protected static function findItem( int $id, Menu $menu, Collection $menuItems ): ?\stdClass
    {
        return $menu->item( $id ) ?? $menuItems->get( $id );
    }
}
