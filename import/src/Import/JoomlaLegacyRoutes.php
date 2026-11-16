<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Builds the URL paths of the Joomla articles and categories without own menu item like the legacy router of Joomla 3.
 *
 * Joomla 3 uses the legacy router unless the "sef_advanced" option of the articles component is on. Used by
 * JoomlaRoutes, which selects the menu item, if JoomlaRouteOptions::$legacy is on. The legacy router adds the
 * ID only to the first category segment.
 */
final class JoomlaLegacyRoutes
{
    /** URL path of the articles component Joomla uses for the links without menu item */
    private const COMPONENT_PATH = 'component/content';


    /**
     * Initializes the object.
     *
     * @param JoomlaRouteCategories $categories Categories Joomla routes, shared by the routes of all languages
     * @param bool $noIds TRUE if the first category segment contains no ID, the hidden "sef_advanced_link" option
     */
    public function __construct(
        private JoomlaRouteCategories $categories,
        private bool $noIds
    ) {
    }


    /**
     * Returns the URL path like the legacy router of Joomla 3 builds it.
     *
     * The legacy router uses the given menu item followed by the aliases of the categories below its category and
     * the given segment. Only the first category segment contains an ID, which is the ID of the category of the
     * article or of the category itself.
     *
     * @param \stdClass|null $item Menu item of the nearest category, the categories list or the home menu item, NULL if there's none
     * @param int $catId ID of the category of the article or of the category without own menu item
     * @param JoomlaMenuItem::VIEW_ARTICLE|JoomlaMenuItem::VIEW_CATEGORY $view View of the URL, "article" or "category"
     * @param string $segment Segment of the article or an empty string for categories
     * @return string|null URL path or NULL if Joomla doesn't build a URL path
     */
    public function route( ?\stdClass $item, int $catId, string $view, string $segment = '' ): ?string
    {
        if( !$item || !( $lineage = $this->categories->lineage( $catId ) ) ) {
            return null;
        }

        $query = JoomlaMenuItem::query( $item );

        if( JoomlaMenuItem::contentComponent( $query ) )
        {
            $base = JoomlaMenuItem::path( $item );
            $top = JoomlaUri::id( $query['id'] ?? null );
        }
        else
        {
            // menu items of other components aren't used, Joomla adds the component and the view instead
            $base = self::COMPONENT_PATH . '/' . $view;
            $top = 0;
        }

        return JoomlaRoutePath::join( [$base, ...$this->segments( $catId, $lineage, $top ), $segment] );
    }


    /**
     * Returns the aliases of the categories below the category of the menu item like the legacy router of Joomla 3.
     *
     * Categories without alias are skipped, so the ID is added to the first category segment with alias.
     *
     * @param int $catId ID of the category whose ID is added to the first segment
     * @param list<int> $lineage IDs of the category and its parents, the category first
     * @param int $top ID of the category of the menu item, 0 if there's none
     * @return list<string> Segments from the top to the category
     */
    private function segments( int $catId, array $lineage, int $top ): array
    {
        // the root category isn't part of the path
        $below = JoomlaRouteCategories::below( $lineage, fn( $id ) => $id === $top || JoomlaRow::isRootId( $id ) );
        $aliases = array_map( fn( $id ) => JoomlaRow::storedAlias( $this->categories->get( $id ) ), array_reverse( $below ) );
        $segments = array_values( array_filter( $aliases, fn( $alias ) => $alias !== '' ) );

        if( !$this->noIds && $segments ) {
            $segments[0] = $catId . '-' . $segments[0];
        }

        return $segments;
    }
}
