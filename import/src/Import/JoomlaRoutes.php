<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Builds the URL paths of the Joomla articles and categories without own menu item like the Joomla router
 * and returns the menu items it uses for them.
 *
 * Articles and categories with own menu item use the URL path of that menu item, which JoomlaPaths returns. The
 * ones without use the path of the menu item of their nearest parent category or of a list of the categories
 * followed by the segments of the categories below and the article. JoomlaPaths builds the URL paths of the rows
 * from these routes and JoomlaUrlAssignment claims them. If JoomlaRouteOptions::$legacy is on, the URL paths are
 * built by JoomlaLegacyRoutes like the legacy router of Joomla 3 from the menu items selected here.
 */
class JoomlaRoutes
{
    /** Pseudo view the home menu items are registered for because Joomla uses them for all views */
    protected const HOME = 'home';

    /** @var array<int|string, \stdClass> Menu items of category blogs and lists by category ID */
    protected array $categoryMenuItems = [];

    /** @var array<int|string, \stdClass> Menu items listing the categories by category ID, 0 for all categories */
    protected array $listItems = [];

    /** @var array<string, \stdClass> Menu items of the views without ID like the featured articles by view and layout, e.g. "featured:blog" or "featured:" */
    protected array $viewItems = [];

    /** Home menu item Joomla uses if there's no menu item for the categories */
    protected ?\stdClass $home = null;

    /** Builds the URL paths like the legacy router of Joomla 3 or NULL if the modern router is used */
    protected ?JoomlaLegacyRoutes $legacy = null;


    /**
     * Collects the menu items used for the URLs of the articles and categories without own menu item.
     *
     * The menu items are selected by the menu rules of Joomla, see JoomlaMenuRules. Only categories Joomla routes are
     * passed, which are published and guests can see, so the articles and categories below others get no URL path.
     *
     * @param JoomlaRouteCategories $categories Categories Joomla routes, shared by the routes of all languages
     * @param iterable<\stdClass> $items Public menu items ordered by their position in the menu
     * @param JoomlaRouteOptions $options Site-wide options of the Joomla router
     * @param JoomlaMenuRules $rules New menu rules of the site from JoomlaSite::menuRules() which select the menu items
     */
    public function __construct( protected JoomlaRouteCategories $categories, iterable $items, protected JoomlaRouteOptions $options,
        JoomlaMenuRules $rules )
    {
        if( $options->legacy ) {
            $this->legacy = new JoomlaLegacyRoutes( $categories, $options->noIds );
        }

        foreach( $items as $item )
        {
            // URL menu items linking to articles and categories aren't used by the Joomla router
            if( JoomlaMenuItem::type( $item ) === JoomlaMenuItem::TYPE_COMPONENT ) {
                $this->collect( $rules, $item );
            }
        }
    }


    /**
     * Returns the URL path of an article without own menu item.
     *
     * @param \stdClass $article Article with ID, alias and category ID
     * @return string|null URL path or NULL if Joomla has no menu item for the categories of the article
     */
    public function article( \stdClass $article ): ?string
    {
        if( ( $segment = $this->segment( $article->id, JoomlaRow::storedAlias( $article ) ) ) === '' ) {
            return null;
        }

        return $this->articleRoute( JoomlaArticle::catId( $article ), $segment );
    }


    /**
     * Returns the URL path of a category without own menu item.
     *
     * @return string|null URL path or NULL if Joomla has no menu item for the parent categories
     */
    public function category( int $catId ): ?string
    {
        if( !( $category = $this->categories->get( $catId ) ) ) {
            return null;
        }

        // categories without alias get no URL path in both routers
        if( ( $alias = JoomlaRow::storedAlias( $category ) ) === '' ) {
            return null;
        }

        return $this->categoryRoute( $catId, $category, $alias );
    }


    /**
     * Returns the URL path of the article in the category.
     *
     * @param int $catId ID of the category of the article
     * @param string $segment Segment of the article
     * @return string|null URL path or NULL if Joomla has no menu item for the categories of the article
     */
    protected function articleRoute( int $catId, string $segment ): ?string
    {
        if( $this->legacy ) {
            // the legacy router uses the home menu item if there's no menu item for the categories
            return $this->legacy->route( $this->item( $catId ) ?? $this->home, $catId, JoomlaMenuItem::VIEW_ARTICLE, $segment );
        }

        return $this->route( $catId, $segment ) ?? $this->homeRoute( $segment );
    }


    /**
     * Returns the URL path of the category with alias.
     *
     * @param int $catId ID of the category
     * @param \stdClass $category Routed category
     * @param string $alias Stored alias of the category
     * @return string|null URL path or NULL if Joomla has no menu item for the parent categories
     */
    protected function categoryRoute( int $catId, \stdClass $category, string $alias ): ?string
    {
        if( $this->legacy ) {
            return $this->legacy->route( $this->legacyCategoryItem( $catId, (int) $category->parent_id ), $catId, JoomlaMenuItem::VIEW_CATEGORY );
        }

        $segment = $this->segment( $catId, $alias );

        // Joomla uses the menu item listing the subcategories of the category if there's no nearer category menu item
        $item = $this->routedItem( $catId );

        if( $item && $item === ( $this->listItems[$catId] ?? null ) ) {
            return JoomlaMenuItem::path( $item );
        }

        return $this->route( (int) $category->parent_id, $segment );
    }


    /**
     * Returns the menu item the legacy router of Joomla 3 uses for the category without own menu item.
     *
     * Like in the modern router, the home menu item of a category isn't used, the menu items of the parent categories are
     * used instead and there's no URL path if they have none. Otherwise, the home menu item is used if no menu item links
     * to the category or its parents.
     *
     * @param int $catId ID of the category
     * @param int $parentId ID of the parent category
     * @return \stdClass|null Menu item or NULL if there's none
     */
    protected function legacyCategoryItem( int $catId, int $parentId ): ?\stdClass
    {
        if( ( $own = $this->categoryItem( $catId ) ) && JoomlaMenuItem::home( $own ) ) {
            return $this->item( $parentId );
        }

        return $this->item( $catId ) ?? $this->home;
    }


    /**
     * Returns the menu item of the category blog or list.
     *
     * @return \stdClass|null Menu item or NULL if no menu item links to the category
     */
    public function categoryItem( int $catId ): ?\stdClass
    {
        return $this->categoryMenuItems[$catId] ?? null;
    }


    /**
     * Returns the own menu item of the category blog or list, the home menu item isn't the one of the category.
     *
     * @return \stdClass|null Menu item or NULL if no menu item except the home menu item links to the category
     */
    public function ownCategoryItem( int $catId ): ?\stdClass
    {
        return JoomlaMenuItem::withoutHome( $this->categoryItem( $catId ) );
    }


    /**
     * Returns the menu item listing the categories.
     *
     * @param int $catId ID of the parent category of the list, 0 or 1 for the root category
     * @return \stdClass|null Menu item or NULL if no menu item lists the categories
     */
    public function listItem( int $catId ): ?\stdClass
    {
        return $this->listItems[JoomlaRow::isRootId( $catId ) ? 0 : $catId] ?? null;
    }


    /**
     * Returns the menu item Joomla uses for links to a view without ID like the featured articles or the archive.
     *
     * Like Joomla, the menu items with the layout of the link are used first, otherwise the ones without layout.
     *
     * @param string $view View of the link, "featured" or "archive"
     * @param string $layout Layout of the link as it is, e.g. "blog" or "default", or an empty string if there's none
     * @return \stdClass|null Menu item or NULL if no menu item shows the view
     */
    public function viewItem( string $view, string $layout = '' ): ?\stdClass
    {
        return $this->viewItems[JoomlaMenuRules::layoutTarget( $view, $layout )] ?? $this->viewItems[JoomlaMenuRules::layoutTarget( $view )] ?? null;
    }


    /**
     * Returns the active menu item Joomla uses for the options and modules of the articles and categories without own menu item.
     *
     * Joomla uses the home menu item if no menu item links to the category or its parents unless strict routing is on.
     *
     * @return \stdClass|null Menu item or NULL if there's none
     */
    public function active( int $catId ): ?\stdClass
    {
        return $this->item( $catId ) ?? ( $this->options->strict ? null : $this->home );
    }


    /**
     * Stores the menu item if the menu rules select it for the home page, a category, a list of categories or a view without ID.
     *
     * @param JoomlaMenuRules $rules Menu rules of the menu items collected before
     * @param \stdClass $item Menu item of a component
     */
    protected function collect( JoomlaMenuRules $rules, \stdClass $item ): void
    {
        $link = JoomlaMenuLink::of( $item );

        if( JoomlaMenuItem::home( $item ) && $rules->take( JoomlaMenuRules::target( self::HOME, 0 ), $item, $link->layout ) ) {
            $this->home = $item;
        }

        if( $link->kind === JoomlaMenuLink::KIND_CATEGORY )
        {
            // lists of the root category have no ID
            if( $link->id && $rules->take( JoomlaMenuRules::target( JoomlaMenuItem::VIEW_CATEGORY, $link->id ), $item, $link->layout ) ) {
                $this->categoryMenuItems[$link->id] = $item;
            }
        }
        elseif( $link->kind === JoomlaMenuLink::KIND_CATEGORIES )
        {
            if( $rules->take( JoomlaMenuRules::target( JoomlaMenuItem::VIEW_CATEGORIES, $link->id ?? 0 ), $item, $link->layout ) ) {
                $this->listItems[$link->id ?? 0] = $item;
            }
        }
        elseif( $link->view !== null && in_array( $link->view, JoomlaMenuItem::VIEWS_WITHOUT_ID, true ) )
        {
            // each layout of the views without ID is a separate target, so the menu items with a layout don't replace the one without
            $target = JoomlaMenuRules::layoutTarget( $link->view, $link->layout );

            if( $rules->take( $target, $item, $link->layout ) ) {
                $this->viewItems[$target] = $item;
            }
        }
    }


    /**
     * Returns the menu item Joomla uses for the articles and subcategories of the category.
     *
     * The menu item of the nearest category is used first, then the one listing the subcategories of the nearest category
     * and the one listing all categories.
     *
     * @return \stdClass|null Menu item of the nearest category or the categories list or NULL if there's none
     */
    protected function item( int $catId ): ?\stdClass
    {
        $lineage = $this->categories->lineage( $catId );
        return self::nearest( $this->categoryMenuItems, $lineage ) ?? self::nearest( $this->listItems, [...$lineage, 0] );
    }


    /**
     * Returns the menu item of the first category which has one.
     *
     * @param array<int|string, \stdClass> $items Menu items by category ID
     * @param list<int> $ids Category IDs, the nearest category first
     * @return \stdClass|null Menu item or NULL if none of the categories has one
     */
    protected static function nearest( array $items, array $ids ): ?\stdClass
    {
        foreach( $ids as $id )
        {
            if( isset( $items[$id] ) ) {
                return $items[$id];
            }
        }

        return null;
    }


    /**
     * Returns the URL path of the article if Joomla uses the home menu item for it.
     *
     * The Joomla 4+ router uses the home menu item if there's no menu item for the categories and adds only the
     * segment of the article if the home menu item is a category blog or list or lists the categories. With strict routing, Joomla
     * doesn't use the home menu item and the URL path starts with the component instead.
     *
     * @param string $segment Segment of the article
     * @return string|null URL path or NULL if the home menu item doesn't show categories or strict routing is on
     */
    protected function homeRoute( string $segment ): ?string
    {
        if( $this->options->strict ) {
            return null;
        }

        return in_array( JoomlaMenuItem::view( $this->home ), JoomlaMenuItem::CATEGORY_VIEWS, true ) ? $segment : null;
    }


    /**
     * Returns the menu item Joomla uses for the category if it routes the category.
     *
     * Joomla routes the categories which are published, which guests can see and whose parents are routed too.
     *
     * @return \stdClass|null Menu item or NULL if the category isn't routed or there's no menu item for it
     */
    protected function routedItem( int $catId ): ?\stdClass
    {
        return JoomlaRow::isRootId( $catId ) || $this->categories->lineage( $catId ) !== [] ? $this->item( $catId ) : null;
    }


    /**
     * Returns the URL path of the menu item of the nearest category followed by the segments of the categories below and the given segment.
     *
     * @param int $catId ID of the category whose menu item is searched
     * @param string $segment Last segment of the URL path
     * @return string|null URL path or NULL if there's no menu item for the category or its parents
     */
    protected function route( int $catId, string $segment ): ?string
    {
        if( !( $item = $this->routedItem( $catId ) ) ) {
            return null;
        }

        // the menu item is a category blog or list or lists the subcategories, the root category has no ID in its link
        $target = JoomlaMenuItem::category( $item ) ?? JoomlaRow::ROOT_ID;
        $below = JoomlaRouteCategories::below( $this->categories->lineage( $catId ), fn( $id ) => $id === $target );
        $segments = array_map( fn( $id ) => $this->segment( $id, JoomlaRow::storedAlias( $this->categories->get( $id ) ) ), $below );

        return JoomlaRoutePath::join( [JoomlaMenuItem::path( $item ), ...array_reverse( $segments ), $segment] );
    }


    /**
     * Returns the URL segment of the article or category.
     *
     * @return string Alias with or without ID or an empty string if there's no alias
     */
    protected function segment( int|string $id, string $alias ): string
    {
        if( $alias === '' ) {
            return '';
        }

        return $this->options->noIds ? $alias : $id . '-' . $alias;
    }
}
