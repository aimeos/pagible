<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Classifies the Joomla menu items by what they show.
 */
class MenuItem
{
    /** Types of the menu items of the components, URLs and aliases of other menu items */
    public const TYPE_COMPONENT = 'component';
    public const TYPE_URL = 'url';
    public const TYPE_ALIAS = 'alias';

    /** Views of the articles component */
    public const VIEW_ARTICLE = 'article';
    public const VIEW_CATEGORY = 'category';
    public const VIEW_CATEGORIES = 'categories';
    public const VIEW_FEATURED = 'featured';
    public const VIEW_ARCHIVE = 'archive';

    /** Views showing a category or listing its subcategories */
    public const CATEGORY_VIEWS = [self::VIEW_CATEGORY, self::VIEW_CATEGORIES];

    /** Views without ID showing the articles of all categories */
    public const VIEWS_WITHOUT_ID = [self::VIEW_FEATURED, self::VIEW_ARCHIVE];

    /** Layout of the menu items without explicit layout */
    public const LAYOUT_DEFAULT = 'default';
    /** Blog layout of the category menu items */
    public const LAYOUT_BLOG = 'blog';

    /** Query parameter of the Joomla links containing the ID of the menu item */
    public const ITEM_ID = 'Itemid';

    /** Menu item types imported as pages without content */
    protected const FOLDER_TYPES = ['heading', 'separator'];

    /** Menu item types imported as link pages */
    public const LINK_TYPES = [self::TYPE_URL, self::TYPE_ALIAS];

    /** Links of URL menu items which are used as parents of their children */
    protected const EMPTY_LINKS = ['', '#'];


    /**
     * Returns the query parameters of the link of the menu item.
     *
     * @param \stdClass|null $menuItem Menu item or NULL if there's none
     * @return array<int|string, mixed> Query parameters like "option", "view", "id" and "layout"
     */
    public static function query( ?\stdClass $menuItem ): array
    {
        return Uri::query( (string) parse_url( (string) ( $menuItem->link ?? '' ), PHP_URL_QUERY ) );
    }


    /**
     * Returns the view of the articles component the menu item shows.
     *
     * Menu items of other types like URLs aren't views of the articles component even if their links are.
     *
     * @param \stdClass|null $menuItem Menu item or NULL if there's none
     * @return string|null View like "article" or "category" or NULL if the menu item doesn't show a view of the articles component
     */
    public static function view( ?\stdClass $menuItem ): ?string
    {
        return $menuItem ? MenuLink::of( $menuItem )->view : null;
    }


    /**
     * Tests if Joomla builds the URLs of the articles, categories and views without ID from the menu item.
     *
     * These are the menu items showing categories, the featured articles or the archive and the home menu items.
     */
    public static function routing( \stdClass $menuItem ): bool
    {
        return in_array( self::view( $menuItem ), [...self::CATEGORY_VIEWS, ...self::VIEWS_WITHOUT_ID], true ) || self::home( $menuItem );
    }


    /**
     * Returns the type of the menu item, menu items without type are components like in Joomla.
     *
     * @return string Type like "component", "url", "alias", "heading" or "separator"
     */
    public static function type( \stdClass $menuItem ): string
    {
        return (string) ( $menuItem->type ?? self::TYPE_COMPONENT );
    }


    /**
     * Returns the layout of the view the menu item shows.
     *
     * @param \stdClass|null $menuItem Menu item or NULL if there's none
     * @return string Layout like "blog" or an empty string if the menu item uses the default layout or shows no view
     */
    public static function layout( ?\stdClass $menuItem ): string
    {
        return $menuItem ? MenuLink::of( $menuItem )->layout : '';
    }


    /**
     * Returns the layout like Joomla compares it, the default layout is the same as none.
     *
     * @param string $layout Layout like "blog" or "default"
     * @return string Layout or an empty string for the default layout
     */
    public static function normalLayout( string $layout ): string
    {
        return $layout === self::LAYOUT_DEFAULT ? '' : $layout;
    }


    /**
     * Returns the ID of the article a menu item shows.
     *
     * @return int|null Article ID or NULL if the menu item shows no article
     */
    public static function article( \stdClass $menuItem ): ?int
    {
        return MenuLink::of( $menuItem )->article();
    }


    /**
     * Returns the ID of the category a menu item shows or lists the subcategories of.
     *
     * @return int|null Category ID or NULL if the menu item shows no category or lists the root category
     */
    public static function category( \stdClass $menuItem ): ?int
    {
        return MenuLink::of( $menuItem )->category();
    }


    /**
     * Tests if the menu item is a home menu item, there's one for each language.
     */
    public static function home( \stdClass $menuItem ): bool
    {
        return !empty( $menuItem->home );
    }


    /**
     * Returns the menu item unless it's a home menu item, whose page is the home page.
     *
     * @return \stdClass|null Menu item or NULL if there's none or it's a home menu item
     */
    public static function withoutHome( ?\stdClass $menuItem ): ?\stdClass
    {
        return $menuItem && !self::home( $menuItem ) ? $menuItem : null;
    }


    /**
     * Returns the URL path Joomla routes the menu item to, the home menu item has none.
     *
     * Menu items without path use their alias or the slug of their title. The routes of the articles and categories
     * start with this path, so they have no prefix for the home menu item. The URL paths of the pages of the menu items
     * are returned by Paths::itemPath(), which uses the alias for home menu items instead.
     */
    public static function path( \stdClass $menuItem ): string
    {
        if( self::home( $menuItem ) ) {
            return '';
        }

        return trim( (string) ( $menuItem->path ?? '' ), '/' ) ?: Row::aliasOrSlug( $menuItem );
    }


    /**
     * Tests if the query parameters of a menu item link are the ones of the articles component.
     *
     * @param array<int|string, mixed> $query Query parameters like "option" and "view"
     */
    public static function contentComponent( array $query ): bool
    {
        return ( $query['option'] ?? null ) === Extensions::COMPONENT_CONTENT;
    }


    /**
     * Returns the view of the articles component from the query parameters of a menu item link.
     *
     * @param array<int|string, mixed> $query Query parameters like "option" and "view"
     * @return string|null View like "article" or NULL if the link isn't one of the articles component
     */
    public static function queryView( array $query ): ?string
    {
        $view = $query['view'] ?? null;
        return self::contentComponent( $query ) && is_string( $view ) ? $view : null;
    }


    /**
     * Tests if the menu item is imported as folder page.
     *
     * Besides headings and separators, URL menu items without URL or with "#" are used as parents of their children.
     */
    public static function folderType( \stdClass $menuItem ): bool
    {
        $type = self::type( $menuItem );
        $link = trim( (string) ( $menuItem->link ?? '' ) );
        return in_array( $type, self::FOLDER_TYPES, true ) || ( $type === self::TYPE_URL && in_array( $link, self::EMPTY_LINKS, true ) );
    }
}
