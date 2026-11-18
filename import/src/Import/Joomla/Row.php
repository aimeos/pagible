<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Utils;


/**
 * Returns the common values of the Joomla articles, categories, menu items and other rows.
 */
class Row
{
    /** ID (not level) of the Joomla root category and root menu item, the top level items are its children */
    public const ROOT_ID = 1;
    /** Level (not ID) of the Joomla root category and root menu item */
    protected const ROOT_LEVEL = 0;
    /** Level (not ID) of the top level categories and menu items, the children of the root */
    public const TOP_LEVEL = self::ROOT_LEVEL + 1;

    /** Joomla state of published rows */
    public const PUBLISHED = 1;
    /** Joomla state of archived rows */
    public const ARCHIVED = 2;
    /** Joomla states of the articles and categories shown on the site, published and archived */
    public const VISIBLE_STATES = [self::PUBLISHED, self::ARCHIVED];


    /**
     * Tests if the article, category, language or other row is published, rows without state are.
     */
    public static function published( \stdClass $row ): bool
    {
        return self::state( $row ) === self::PUBLISHED;
    }


    /**
     * Returns the state of the article, category, language or other row like Row::PUBLISHED, rows without state are published.
     *
     * Articles store their state in the "state" column, the other rows in the "published" column.
     */
    public static function state( \stdClass $row ): int
    {
        return (int) ( $row->published ?? $row->state ?? self::PUBLISHED );
    }


    /**
     * Returns the alias of the article, category or menu item or the slug of its title if it has none.
     */
    public static function aliasOrSlug( \stdClass $row ): string
    {
        return self::storedAlias( $row ) ?: Utils::slugify( self::title( $row ) );
    }


    /**
     * Tests if the article, category, menu item, module, field or tag is shown for all languages.
     *
     * @param \stdClass|null $row Joomla row or NULL if there's none
     */
    public static function allLanguages( ?\stdClass $row ): bool
    {
        return self::language( $row ) === Languages::ALL;
    }


    /**
     * Tests if the row is in the language of the tag or for all languages.
     *
     * Unlike Site::shows(), rows for all languages don't match rows of other languages and the language filter isn't checked.
     *
     * @param string $tag Joomla language tag
     */
    public static function inLanguage( \stdClass $row, string $tag ): bool
    {
        return self::allLanguages( $row ) || self::language( $row ) === $tag;
    }


    /**
     * Returns the ID and title of the article, category, menu item or module for messages, e.g. "[1] About".
     */
    public static function label( \stdClass $row ): string
    {
        return '[' . ( $row->id ?? '' ) . '] ' . self::title( $row );
    }


    /**
     * Returns the title of the article, category, menu item, module or tag as stored, i.e. untrimmed.
     *
     * Use menuTitle() for the titles of menu items Joomla trims.
     *
     * @param \stdClass|null $row Row or NULL if there's none
     * @return string Untrimmed title or an empty string if there's none
     */
    public static function title( ?\stdClass $row ): string
    {
        return (string) ( $row->title ?? '' );
    }


    /**
     * Returns the trimmed title of the menu item used for the page title, page name and the headings.
     *
     * Joomla trims the menu item title before it replaces the title of the linked article, category or page.
     *
     * @param \stdClass|null $item Menu item or NULL if there's none
     * @return string Trimmed title or an empty string if there's none
     */
    public static function menuTitle( ?\stdClass $item ): string
    {
        return Value::string( $item->title ?? null );
    }


    /**
     * Returns the Joomla language tag of the article, category, menu item, module, field or tag.
     *
     * @param \stdClass|null $row Joomla row or NULL if there's none
     * @return string Language tag like "en-GB" or "*" for all languages or no row
     */
    public static function language( ?\stdClass $row ): string
    {
        return (string) ( $row->language ?? Languages::ALL );
    }


    /**
     * Returns the decoded options of the category, menu item, module or field.
     *
     * @param \stdClass|null $row Joomla row or NULL if there's none
     * @return array<string, mixed> Options by name or an empty array if there are none
     */
    public static function params( ?\stdClass $row ): array
    {
        return Value::decode( $row->params ?? null );
    }


    /**
     * Tests if the category or menu item ID is the root or no parent at all.
     *
     * The root category and root menu item have the ID 1 but most menu item links use 0 or no ID for the root category.
     */
    public static function isRootId( int $id ): bool
    {
        return $id <= self::ROOT_ID;
    }


    /**
     * Tests if the category or menu item is the root of all categories or menus.
     *
     * Rows without level are the root if they have its ID.
     */
    public static function isRootRow( \stdClass $row ): bool
    {
        return isset( $row->level ) ? (int) $row->level <= self::ROOT_LEVEL : (int) ( $row->id ?? 0 ) === self::ROOT_ID;
    }


    /**
     * Returns the stored alias of the article, category or menu item.
     *
     * @param \stdClass|null $row Joomla row or NULL if it doesn't exist
     * @return string Alias or an empty string if there's none
     */
    public static function storedAlias( ?\stdClass $row ): string
    {
        return (string) ( $row->alias ?? '' );
    }


    /**
     * Returns the rows of the IDs in the order of the IDs, missing rows are skipped.
     *
     * @template T
     * @param array<int|string, T> $rows Rows by ID
     * @param array<int|string> $ids IDs of the rows
     * @return list<T> Rows in the order of the IDs
     */
    public static function pick( array $rows, array $ids ): array
    {
        $list = [];

        foreach( $ids as $id )
        {
            if( isset( $rows[$id] ) ) {
                $list[] = $rows[$id];
            }
        }

        return $list;
    }
}
