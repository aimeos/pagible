<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Detects the articles and translations changed since the date of the --since option.
 *
 * Articles are changed too if their publishing dates, the ones of the menu items linking to them or their categories
 * or the modification times of their categories or one of its parents passed since the date.
 */
class Tracker
{
    /** Modification time of the articles whose time isn't known, they are always changed like by Commands::UNKNOWN_TIME */
    public const UNKNOWN_TIME = PHP_INT_MAX;


    /**
     * Initializes the tracker.
     *
     * @param Reader $reader Reader for the Joomla database
     * @param Site $site Categories and access checks of the Joomla site
     */
    public function __construct( protected Reader $reader, protected Site $site )
    {
    }


    /**
     * Returns the last modification times of the articles including their translations and the publishing dates passed since the date.
     *
     * @param Articles $articles Imported articles with the modification times of the articles and translations
     * @param int $since Timestamp of the --since date
     * @return array<int|string, int> Latest modification times by article ID, unknown ones are the newest
     */
    public function changed( Articles $articles, int $since ): array
    {
        return static::times( $articles, $this->scheduled( $articles, $since ) );
    }


    /**
     * Returns the last modification times of the articles including their translations.
     *
     * The publishing dates passed since the --since date are changes too, articles without known modification time stay changed.
     * The article of the root page is rendered in every run, so the articles of the home menu items of the other languages
     * shown by the variants of the root page aren't included.
     *
     * @param Articles $articles Imported articles with the modification times of the articles and translations
     * @param array<int|string, int> $scheduled Publishing dates passed since the --since date by article ID, see scheduled()
     * @return array<int|string, int> Latest modification times by article ID, unknown ones are the newest
     */
    public static function times( Articles $articles, array $scheduled = [] ): array
    {
        $changed = [];
        $times = $articles->times;

        // articles and translations without known modification time are changed anyway, the publishing dates don't change that
        foreach( $scheduled as $id => $time ) {
            $times[$id] = max( $times[$id] ?? static::UNKNOWN_TIME, $time );
        }

        foreach( array_keys( $articles->categoryIds ) as $id )
        {
            $latest = $times[$id] ?? static::UNKNOWN_TIME;

            foreach( $articles->translations[$id] ?? [] as $tid ) {
                $latest = max( $latest, $times[$tid] ?? static::UNKNOWN_TIME );
            }

            $changed[$id] = $latest;
        }

        return $changed;
    }


    /**
     * Returns the publishing dates of the articles and the menu items linking to them which passed since the date.
     *
     * Joomla doesn't change the articles and menu items when they are published or expire, so the pages of
     * articles whose publishing dates passed since the date must be imported again. Dates in the future aren't
     * changes yet, they are found by the next import after they passed. The dates of the menu items linking to
     * a category and the modification times of the categories count for the imported articles and translations of
     * the category and its subcategories because Joomla shows the articles without own menu item using the menu
     * items and the access, state, name and alias of the categories decide about the access and the URLs of the
     * articles. The dates of other menu items like URLs or aliases aren't used, pages whose URL path changed
     * because of them aren't found by their previous path and are rendered anyway.
     *
     * @param Articles $articles Imported articles and translations whose categories are checked
     * @param int $since Timestamp of the --since date
     * @return array<int|string, int> Latest passed publishing or category modification date as timestamp by article ID
     */
    public function scheduled( Articles $articles, int $since ): array
    {
        // the same time as used by the access checks of the articles and menu items
        $now = $this->site->access->now;
        $times = $this->reader->scheduled( $since, $now );
        $categoryTimes = $this->modifiedCategories( $since, $now );

        // the menu items linking to the articles and categories decide about their URLs, titles and options
        foreach( $this->reader->menuItems() as $item )
        {
            $latest = null;

            foreach( [$item->publish_up ?? null, $item->publish_down ?? null] as $date )
            {
                if( ( $time = Date::between( $date, $since, $now ) ) !== null ) {
                    $latest = max( $latest ?? $time, $time );
                }
            }

            if( $latest === null ) {
                continue;
            }

            if( ( $id = MenuItem::article( $item ) ) !== null ) {
                $times[$id] = max( $times[$id] ?? $latest, $latest );
            } elseif( ( $catId = MenuItem::category( $item ) ) !== null ) {
                $categoryTimes[$catId] = max( $categoryTimes[$catId] ?? $latest, $latest );
            }
        }

        if( $categoryTimes === [] ) {
            return $times;
        }

        // the latest date of each category and its parents, computed once per category
        $lineageTimes = [];

        foreach( $this->site->categories->keys() as $catId )
        {
            $lineage = CategoryTree::lineage( $this->site->categories, (int) $catId )->categories;

            if( ( $time = max( [0, ...array_intersect_key( $categoryTimes, $lineage )] ) ) > 0 ) {
                $lineageTimes[$catId] = $time;
            }
        }

        // the translations are in categories of their own languages, so the articles are fetched by category
        foreach( $lineageTimes ? $this->reader->categoryArticles( array_keys( $lineageTimes ) ) : [] as $id => $catId )
        {
            if( isset( $articles->visible[$id] ) ) {
                $times[$id] = max( $times[$id] ?? $lineageTimes[$catId], $lineageTimes[$catId] );
            }
        }

        return $times;
    }


    /**
     * Returns the modification times of the categories changed since the date.
     *
     * Joomla updates the modification time when a category is saved, e.g. if its access level, state, title,
     * alias or parent changed, but not the ones of its articles and subcategories. Like the publishing dates,
     * times in the future aren't changes yet, they are found by the next import after they passed.
     *
     * @param int $since Timestamp of the --since date
     * @param int $now Timestamp of the current time used by the access checks
     * @return array<int|string, int> Modification times as timestamp by category ID
     */
    protected function modifiedCategories( int $since, int $now ): array
    {
        $times = [];

        foreach( $this->site->categories as $catId => $category )
        {
            if( ( $time = Date::between( $category->modified_time ?? null, $since, $now ) ) !== null ) {
                $times[$catId] = $time;
            }
        }

        return $times;
    }
}
