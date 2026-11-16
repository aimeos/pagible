<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Types of the Joomla rows imported as pages or translations.
 *
 * They are prefixed to the row IDs for the record keys of the URL paths, e.g. "a5" for article 5
 * or "ta7" for the translated article 7, and used as keys of the rows grouped by type.
 */
final class JoomlaKind
{
    /** Articles */
    public const ARTICLE = 'a';
    /** Categories */
    public const CATEGORY = 'c';
    /** Menu items */
    public const MENU = 'm';
    /** Prefix of the translations, e.g. "ta" for translated articles */
    private const TRANSLATION = 't';
    /** Translated articles */
    public const ARTICLE_TRANSLATION = self::TRANSLATION . self::ARTICLE;


    /**
     * Returns the record key of the row, e.g. "a5" for article 5.
     *
     * @param self::ARTICLE|self::CATEGORY|self::MENU $kind Type of the row
     * @param int|string $id ID of the row
     */
    public static function key( string $kind, int|string $id ): string
    {
        return $kind . $id;
    }


    /**
     * Returns the record key of the translated row, e.g. "ta7" for the translated article 7.
     *
     * @param self::ARTICLE|self::CATEGORY|self::MENU $kind Type of the translated row
     * @param int|string $id ID of the translated row
     */
    public static function translationKey( string $kind, int|string $id ): string
    {
        return self::TRANSLATION . self::key( $kind, $id );
    }
}
