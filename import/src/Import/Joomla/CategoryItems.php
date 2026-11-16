<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Decides which Joomla categories and menu items of the imported menu get own pages and returns the menu items showing the categories.
 *
 * Categories shown by a menu item of the imported menu use its page, the category of the home menu item uses the
 * root page. Other categories get own pages below the top level or if a menu item of another menu shows them, but
 * only if articles are imported below them, see pages(). The menu items with own pages are selected like
 * ImportsMenu::importMenuItem() imports them, so the URL paths are only claimed for pages which are created.
 * The category of the home menu item of the site is computed once, see homeCatId().
 */
class CategoryItems
{
    /** @var array<int|string, \stdClass> Menu items of the imported menu with own pages by ID */
    protected array $menuPages = [];

    /** @var array<int|string, \stdClass> Menu items whose pages show the categories by category ID */
    protected array $categoryPageItems = [];

    /** ID of the category the home menu item of the site shows on the root page or NULL if there's none */
    protected ?int $homeCatId = null;


    /**
     * Initializes the object and selects the menu items with own pages.
     *
     * @param Site $site Settings of the Joomla site
     * @param Router $router Returns the Joomla routes of the articles and categories
     * @param Menu $menu Menu items of the imported menu
     * @param Collection<array-key, \stdClass> $items Published menu items of all menus by ID the alias menu items can point to
     * @param \stdClass|null $home Home menu item of the site used for the root page, see Homes::home(), or NULL if the site has none
     */
    public function __construct( protected Site $site, protected Router $router, protected Menu $menu,
        protected Collection $items, ?\stdClass $home )
    {
        $this->homeCatId = $home ? $this->homeCategory( $home ) : null;
        $this->selectPages( $home );
    }


    /**
     * Returns the category whose articles the home menu item of the site shows on the root page.
     *
     * @return int|null Category ID or NULL if the home menu item shows no category on the root page, see homeCategory()
     */
    public function homeCatId(): ?int
    {
        return $this->homeCatId;
    }


    /**
     * Tests if the menu item of the imported menu gets an own page.
     */
    public function hasMenuPage( int|string $id ): bool
    {
        return isset( $this->menuPages[$id] );
    }


    /**
     * Returns the menu items of the imported menu which get own pages.
     *
     * @return array<int|string, \stdClass> Menu items ordered by ID
     */
    public function menuPages(): array
    {
        return $this->menuPages;
    }


    /**
     * Returns the public menu item of the imported menu whose page shows the category.
     *
     * @return \stdClass|null Menu item with own page or the home menu item using the root page, NULL if there's none
     */
    public function categoryPageItem( int|string $catId ): ?\stdClass
    {
        return $this->categoryPageItems[$catId] ?? null;
    }


    /**
     * Returns the category if it gets an own page without menu item of the imported menu.
     *
     * Categories shown by a menu item of the imported menu use its page. Top-level categories only get a page
     * if a menu item of another menu shows them, see otherItem(), hidden categories never.
     *
     * @return \stdClass|null Category or NULL if it has no own page or a menu item of the imported menu shows it
     */
    public function category( int $catId ): ?\stdClass
    {
        if( isset( $this->categoryPageItems[$catId] ) || !( $category = $this->site->category( $catId ) ) ) {
            return null;
        }

        return (int) $category->level > Row::TOP_LEVEL || $this->routeItem( $category ) ? $category : null;
    }


    /**
     * Returns the menu item of another menu linking to the category.
     *
     * The categories get own pages with the URL path of the menu item because the menu item isn't imported.
     * Like category(), the menu items of the imported menu are checked here because the category uses their page then.
     * The home menu item is never returned because Routes::ownCategoryItem() skips it.
     *
     * @return \stdClass|null Menu item or NULL if there's none, if it's the home menu item or if a menu item of the imported menu shows the category
     */
    public function otherItem( \stdClass $category ): ?\stdClass
    {
        return !isset( $this->categoryPageItems[$category->id] ) ? $this->routeItem( $category ) : null;
    }


    /**
     * Returns the category whose articles the home menu item shows on the root page.
     *
     * Like the import of the home menu item, the root page is used for the category if Joomla shows the category and
     * guests can see the menu item and the category, otherwise the home menu item is imported like the other ones.
     * The same check selects the pages of the menu items, so the home menu item gets an own page then, see selectPages().
     *
     * @param \stdClass $home Home menu item of the site used for the root page
     * @return int|null Category ID or NULL if the home menu item shows no category on the root page
     */
    public function homeCategory( \stdClass $home ): ?int
    {
        $link = MenuLink::of( $home );

        if( $link->kind !== MenuLink::KIND_CATEGORY || !$link->id || !$this->site->category( $link->id ) ) {
            return null;
        }

        return $this->site->access->categoryItemRestriction( $home, $link->id ) === null ? $link->id : null;
    }


    /**
     * Returns the categories which get own pages because articles are imported below them.
     *
     * Like the import, the article of the root page and the articles of public menu items of the imported menu are imported
     * elsewhere, the other ones below the pages of their categories, which are created together with the pages of their parent
     * categories. Categories with the page of a menu item of the imported menu or the root page end the chain of parents
     * because category() returns none for them, the category of the home menu item uses the root page, see homeCatId().
     *
     * @param array<int|string, int> $categoryIds Category IDs of the articles imported as pages by article ID
     * @param int|null $homeArticleId ID of the article imported into the root page or NULL if there's none
     * @return array<int, true> IDs of the categories with own pages as keys
     */
    public function pages( array $categoryIds, ?int $homeArticleId = null ): array
    {
        $pages = [];

        foreach( $categoryIds as $id => $catId )
        {
            if( (string) $id === (string) $homeArticleId || $this->menu->importsArticle( $id ) ) {
                continue;
            }

            $catId = (int) $catId;

            // the parents of known categories are known already
            while( !isset( $pages[$catId] ) && ( $category = $this->category( $catId ) ) )
            {
                $pages[$catId] = true;
                $catId = (int) $category->parent_id;
            }
        }

        return $pages;
    }


    /**
     * Tests if the translation of a menu item gets a page variant, so it may claim a URL path.
     *
     * Like the menu items of the imported menu, see selectPages(), translated URL and alias menu items need a target
     * Links::linkable() supports and translated menu items of categories need a category Joomla shows, the ones listing
     * all categories without ID are paged like in the imported menu. The same
     * check decides which translations claim URL paths, see UrlAssignment, and which are imported, see
     * Pages::menuTranslation(), so links never point to URL paths of translations without page variant.
     * Translations which aren't part of the imported menu can point to other translated menu items with page variants.
     *
     * Joomla associates menu items of any type, so the translation can show something else than its source, e.g. a
     * folder translated by a category. The variant shows what the translation shows, like the language switcher links
     * to it, which works for folders, links and categories. Translations showing an article or another view like the
     * featured articles get no variant because the variant of a folder or category page would be empty: the translated
     * article gets its own page variant and the views without ID have no page. Translated home menu items are root
     * page variants, which show the featured articles too, see Pages::home().
     *
     * @param \stdClass $menuItem Translated menu item, usually one of another menu
     * @param \Closure(int|string): bool $variant Tests if the translated menu item with the ID has a page variant
     */
    public function translationPaged( \stdClass $menuItem, \Closure $variant ): bool
    {
        $link = MenuLink::of( $menuItem );
        $kind = $link->kind;

        if( $kind === MenuLink::KIND_LINK ) {
            return Links::menuLinkable( $menuItem, $this->menu, $this->items, $this->router, $variant );
        }

        if( !MenuItem::home( $menuItem ) && in_array( $kind, [MenuLink::KIND_ARTICLE, MenuLink::KIND_OTHER], true ) ) {
            return false;
        }

        // like selectPages(), menu items of a category without ID get no page, the ones listing all categories do
        if( ( $catId = $link->category() ) === null ) {
            return $kind !== MenuLink::KIND_CATEGORY || MenuItem::home( $menuItem );
        }

        // like Joomla, menu items of unpublished or trashed categories aren't shown
        return $this->site->category( $catId ) !== null;
    }


    /**
     * Returns the own menu item Joomla uses for the category, which isn't the home menu item.
     *
     * @return \stdClass|null Menu item or NULL if there's none
     */
    protected function routeItem( \stdClass $category ): ?\stdClass
    {
        return $this->router->contentRoutes( Row::language( $category ) )->ownCategoryItem( (int) $category->id );
    }


    /**
     * Selects the menu items of the imported menu which get own pages and the ones whose pages show the categories.
     *
     * Like ImportsMenu::importMenuItem(), folders, links with supported targets and lists of all categories get own pages,
     * also restricted ones, because their pages are disabled. Links whose targets aren't supported get no page, see Links::linkable(). Menu items of categories only get pages if guests can see them, Joomla shows their
     * categories and no other menu item shows the category already. The home menu item uses the root page unless guests
     * can't see its category, see homeCategory(). Other home menu items, e.g. the ones of other languages without
     * language filter, are imported like the other menu items. The first public menu item in the menu order linking to the
     * category shows it, then the menu items listing its subcategories which Joomla uses if there's no nearer category item.
     *
     * @param \stdClass|null $home Home menu item of the site used for the root page or NULL if there's none
     */
    protected function selectPages( ?\stdClass $home ): void
    {
        $candidates = [];
        $access = $this->site->access;
        $homeCatId = $this->homeCatId;

        // the home menu item, also one of another menu, is imported before the other ones, so its category uses the root page
        if( $home && $homeCatId !== null ) {
            $this->categoryPageItems[$homeCatId] = $home;
        }

        foreach( $this->menu->tree() as $node )
        {
            $item = $node->item;
            $link = MenuLink::of( $item );

            // only home menu items of categories guests can't see are imported like the other menu items
            if( $home && (int) $item->id === (int) $home->id
                && ( $homeCatId !== null || $link->kind !== MenuLink::KIND_CATEGORY || !$link->id )
            ) {
                continue;
            }

            if( $link->kind === MenuLink::KIND_FOLDER
                || ( $link->kind === MenuLink::KIND_LINK && Links::linkable( $item, $this->menu, $this->items, $this->router ) )
                || ( $link->kind === MenuLink::KIND_CATEGORIES && $link->id === null )
            ) {
                $this->menuPages[$item->id] = $item;
            } elseif( ( $catId = $link->category() ) !== null && $access->restriction( $item ) === null && $this->site->category( $catId ) ) {
                $candidates[$link->kind][] = [$catId, $item];
            }
        }

        foreach( MenuLink::CATEGORY_KINDS as $kind )
        {
            foreach( $candidates[$kind] ?? [] as [$catId, $item] )
            {
                if( !isset( $this->categoryPageItems[$catId] ) ) {
                    $this->categoryPageItems[$catId] = $this->menuPages[$item->id] = $item;
                }
            }
        }

        ksort( $this->menuPages );
    }

}
