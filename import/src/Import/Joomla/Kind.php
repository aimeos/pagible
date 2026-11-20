<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Types of the Joomla rows imported as pages or translations.
 *
 * Their values are prefixed to the row IDs for the record keys of the URL paths, e.g. "a5" for article 5
 * or "ta7" for the translated article 7, and used as keys of the rows grouped by type.
 */
enum Kind: string
{
    /** Articles */
    case Article = 'a';
    /** Categories */
    case Category = 'c';
    /** Menu items */
    case Menu = 'm';

    /** Prefix of the translations, e.g. "ta" for translated articles */
    private const TRANSLATION = 't';
    /** Translated articles */
    public const ARTICLE_TRANSLATION = self::TRANSLATION . 'a';


    /**
     * Returns the record key of the row, e.g. "a5" for article 5.
     *
     * @param int|string $id ID of the row
     */
    public function key( int|string $id ): string
    {
        return $this->value . $id;
    }


    /**
     * Returns the name of the rows used in messages, e.g. "article".
     */
    public function label(): string
    {
        return match( $this ) {
            self::Article => 'article',
            self::Category => 'category',
            self::Menu => 'menu item',
        };
    }


    /**
     * Returns the record key of the translated row, e.g. "ta7" for the translated article 7.
     *
     * @param int|string $id ID of the translated row
     */
    public function translationKey( int|string $id ): string
    {
        return self::TRANSLATION . $this->key( $id );
    }
}
