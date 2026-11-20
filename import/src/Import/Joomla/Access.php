<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Collection;


/**
 * Checks if guests can see the Joomla articles, categories, menu items and modules like Joomla.
 *
 * Rows guests can't see because of their access levels or publishing dates are imported as disabled pages
 * because Pagible can't restrict them to the Joomla users or publish them at that time.
 *
 * The methods are named by what they return:
 * - *Restriction() return the reason why guests can't see a row or NULL if they can
 * - restricted() and *Public() test the access levels of guests
 * - levelTitle() returns the title of an access level guests can't see
 * - *Hidden() and stateVisible() test the publishing states, which decide if Joomla shows the articles of a category at all
 */
class Access
{
    use Remembers;

    /** Reasons why guests can't see a row with the placeholders for the date, the title or the access level */
    protected const EXPIRED = 'it expired on %s';
    protected const SCHEDULED = "it's scheduled for %s, enable it then";
    protected const RESTRICTED = "it's restricted to Joomla access level %s";
    protected const ARCHIVED_CATEGORY = 'the category %s is archived';
    protected const CATEGORY_RESTRICTED = 'its category %s is restricted to Joomla access level %s';

    /** Access level of content languages without view level, Joomla treats them as public */
    protected const LANGUAGE_WITHOUT_LEVEL = 0;

    /** @var array<int|string, string|null> Cache of ancestorLevelRestriction() by category ID, NULL for public ones */
    protected array $ancestorLevelCache = [];
    /** @var array<int|string, bool> Cache of categoryHidden() by category ID */
    protected array $categoryHiddenCache = [];
    /** @var array<int|string, string|null> Cache of archivedRestriction() by category ID, NULL if none is archived */
    protected array $archivedCache = [];


    /** Timestamp the publishing dates are compared with, the same for the whole run */
    public readonly int $now;


    /**
     * Initializes the object.
     *
     * @param Levels $levels View levels of the Joomla site
     * @param Collection<array-key, \stdClass> $categories Categories of all states by ID
     * @param int|null $now Timestamp the publishing dates are compared with or NULL for the current time
     */
    public function __construct( protected Levels $levels, protected Collection $categories, ?int $now = null )
    {
        $this->now = $now ?? now()->getTimestamp();
    }


    /**
     * Returns why guests can't see the row at the moment because of its access level or publishing dates.
     *
     * @return string|null Reason or NULL if the row is public
     */
    public function restriction( \stdClass $row ): ?string
    {
        if( ( $level = $this->levelTitle( $row->access ?? null ) ) !== null ) {
            return sprintf( self::RESTRICTED, $level );
        }

        return $this->dateRestriction( $row );
    }


    /**
     * Returns why Joomla doesn't show the row at the moment because of its publishing dates.
     *
     * Joomla stores the dates in UTC. They are compared with the time the object was created, so all rows
     * of the run and the --since checks use the same time.
     *
     * @return string|null Reason or NULL if the row isn't expired or scheduled
     */
    public function dateRestriction( \stdClass $row ): ?string
    {
        $down = Date::time( $row->publish_down ?? null );

        if( $down !== null && $down < $this->now ) {
            return sprintf( self::EXPIRED, Date::format( $down ) );
        }

        $up = Date::time( $row->publish_up ?? null );

        if( $up !== null && $up > $this->now ) {
            return sprintf( self::SCHEDULED, Date::format( $up ) );
        }

        return null;
    }


    /**
     * Tests if guests can't see the access level.
     *
     * @param mixed $access ID of the view level or NULL if there's none
     */
    public function restricted( mixed $access ): bool
    {
        return $this->levelTitle( $access ) !== null;
    }


    /**
     * Tests if guests can use the content language, languages without access level are public.
     */
    public function languagePublic( \stdClass $language ): bool
    {
        $access = $language->access ?? null;
        return ( is_numeric( $access ) && (int) $access === self::LANGUAGE_WITHOUT_LEVEL ) || !$this->restricted( $access );
    }


    /**
     * Tests if the category is published and guests can see it, its parents aren't checked.
     */
    public function categoryPublic( \stdClass $category ): bool
    {
        return Row::published( $category ) && !$this->restricted( $category->access ?? null );
    }


    /**
     * Tests if the category or one of its parents is unpublished or trashed, so Joomla doesn't show their articles.
     *
     * The articles of archived categories are shown like the ones of published categories. Unknown categories are visible.
     */
    public function categoryHidden( int $catId ): bool
    {
        return $this->remember( $this->categoryHiddenCache, $catId, function() use ( $catId ) {
            $hidden = fn( $category ) => !$this->stateVisible( $category );
            return CategoryTree::firstAncestor( $this->categories, $catId, $hidden ) !== null;
        } );
    }


    /**
     * Tests if Joomla doesn't show the articles of the category.
     *
     * Joomla only checks the category itself for articles with own menu item including the home menu item.
     * Articles without own menu item have no URL in Joomla if a parent category is unpublished or trashed.
     * This mirrors the article model of Joomla 3.x (as of 3.10), 4.x and 5.x, whose getItem() only joins the
     * category of the article and requires "c.published > 0". The "badcats" subquery of older Joomla versions
     * like 2.5, which also hid articles below unpublished or trashed parent categories, isn't mirrored.
     *
     * @param bool $own TRUE if a menu item links to the article
     */
    public function articleHidden( int $catId, bool $own ): bool
    {
        if( $own ) {
            return !$this->stateVisible( $this->categories->get( $catId ) );
        }

        return $this->categoryHidden( $catId );
    }


    /**
     * Returns why guests can't see the article.
     *
     * Joomla only checks the access level of the category itself for articles with own menu item, otherwise its parents too.
     *
     * @param bool $own TRUE if a menu item links to the article
     * @param \stdClass|null $showing Menu item showing the article instead of its own one, e.g. the home menu item
     * @return string|null Reason or NULL if guests can see the article
     */
    public function articleRestriction( \stdClass $article, bool $own, ?\stdClass $showing = null ): ?string
    {
        foreach( array_filter( [$article, $showing] ) as $row )
        {
            if( ( $reason = $this->restriction( $row ) ) !== null ) {
                return $reason;
            }
        }

        $catId = Article::catId( $article );
        return $own ? $this->categoryLevelRestriction( $this->categories->get( $catId ) ) : $this->ancestorLevelRestriction( $catId );
    }


    /**
     * Returns why guests can't see the category.
     *
     * The category itself, whether it or one of its parents is archived and the access levels of its parents are
     * always checked, also if a menu item links to the category. Joomla's Categories::_load() only loads published
     * categories guests can see and drops all categories whose parent isn't loaded, so Categories::get() doesn't find
     * categories below archived or restricted parents.
     *
     * @return string|null Reason or NULL if guests can see the category
     */
    public function categoryRestriction( \stdClass $category ): ?string
    {
        return $this->restriction( $category ) ?? $this->treeRestriction( (int) $category->id, $category );
    }


    /**
     * Returns why guests can't see the menu item or the category it links to.
     *
     * Like categoryRestriction(), the access levels of the category and its parents and their archived states are checked.
     *
     * @param int|null $catId ID of the category the menu item links to or NULL if it links to none
     * @return string|null Reason or NULL if guests can see the menu item
     */
    public function categoryItemRestriction( \stdClass $menuItem, ?int $catId ): ?string
    {
        $reason = $this->restriction( $menuItem );

        if( $reason !== null || !$catId ) {
            return $reason;
        }

        return $this->itemCategoryRestriction( $catId );
    }


    /**
     * Returns why guests can't see the category a public menu item links to.
     *
     * Like categoryItemRestriction() for menu items whose own restriction() is known to be NULL already.
     *
     * @param int $catId ID of the category the menu item links to
     * @return string|null Reason or NULL if guests can see the category
     */
    public function itemCategoryRestriction( int $catId ): ?string
    {
        $category = $this->categories->get( $catId );
        return $this->categoryLevelRestriction( $category ) ?? $this->treeRestriction( $catId, $category );
    }


    /**
     * Returns why guests can't see the category because it or one of its parents is archived or a parent is restricted.
     *
     * The checks shared by categoryRestriction() and itemCategoryRestriction() after the own restriction of the category.
     *
     * @param int $catId ID of the category
     * @param \stdClass|null $category Category or NULL if it's unknown, then its parents aren't checked
     * @return string|null Reason or NULL if guests can see the category
     */
    protected function treeRestriction( int $catId, ?\stdClass $category ): ?string
    {
        return $this->archivedRestriction( $catId )
            ?? ( $category ? $this->ancestorLevelRestriction( (int) $category->parent_id ) : null );
    }


    /**
     * Returns why the page of the category is disabled if the category or one of its parents is archived.
     *
     * Joomla doesn't show archived categories and their subcategories but still the articles in them.
     *
     * @return string|null Reason or NULL if neither the category nor its parents are archived
     */
    protected function archivedRestriction( int $catId ): ?string
    {
        return $this->remember( $this->archivedCache, $catId, function() use ( $catId ) {
            $archived = fn( $category ) => Row::state( $category ) === Row::ARCHIVED;
            $category = CategoryTree::firstAncestor( $this->categories, $catId, $archived );

            return $category ? sprintf( self::ARCHIVED_CATEGORY, Row::title( $category ) ) : null;
        } );
    }


    /**
     * Tests if Joomla shows the articles of the category because it's published or archived, its parents aren't checked.
     *
     * @param \stdClass|null $category Category or NULL if it's unknown, then its articles are shown
     */
    protected function stateVisible( ?\stdClass $category ): bool
    {
        return $category === null || in_array( Row::state( $category ), Row::VISIBLE_STATES, true );
    }


    /**
     * Returns why guests can't see the category or one of its parents because of their access levels.
     *
     * Articles without own menu item and all subcategories of categories with an access level which isn't public are disabled too.
     *
     * @param int $catId ID of the category of the article or of the parent category
     * @return string|null Reason or NULL if the categories are public or unknown
     */
    protected function ancestorLevelRestriction( int $catId ): ?string
    {
        $restricted = fn( $category ) => $this->restricted( $category->access ?? null );
        $fetch = fn() => $this->categoryLevelRestriction( CategoryTree::firstAncestor( $this->categories, $catId, $restricted ) );

        return $this->remember( $this->ancestorLevelCache, $catId, $fetch );
    }


    /**
     * Returns why guests can't see the category because of its own access level.
     *
     * @param \stdClass|null $category Category or NULL if it's unknown
     * @return string|null Reason or NULL if the category is public or unknown
     */
    protected function categoryLevelRestriction( ?\stdClass $category ): ?string
    {
        if( $category === null ) {
            return null;
        }

        $level = $this->levelTitle( $category->access ?? null );
        return $level !== null ? sprintf( self::CATEGORY_RESTRICTED, Row::title( $category ), $level ) : null;
    }


    /**
     * Returns the title of the access level if guests can't see it.
     *
     * @param mixed $access ID of the view level or NULL if there's none
     * @return string|null Title or ID of the view level or NULL if it's public or there's no view level
     */
    protected function levelTitle( mixed $access ): ?string
    {
        if( !is_numeric( $access ) ) {
            return null;
        }

        $id = (int) $access;
        return $this->levels->isPublic( $id ) ? null : $this->levels->title( $id );
    }
}
