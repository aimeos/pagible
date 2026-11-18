<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * URL path an imported Joomla row claims, e.g. the alias of an article without URL path in Joomla.
 *
 * The record key is derived from the type and ID of the row, use row() or translation() to create the claims.
 */
final readonly class UrlClaim
{
    /**
     * Initializes the object.
     *
     * @param UrlKey $urlKey Record key of the row from Kind::key() or Kind::translationKey() and the language domain it claims the path in
     * @param int|string $id ID of the row appended to the URL path if it's used already
     * @param string $path Alias or URL path of the row
     */
    private function __construct(
        public UrlKey $urlKey,
        public int|string $id,
        public string $path
    ) {
    }


    /**
     * Returns the claim of an article, category or menu item in the domain of the imported pages.
     *
     * @param Kind::ARTICLE|Kind::CATEGORY|Kind::MENU $kind Type of the row
     * @param int|string $id ID of the row
     * @param string $path Alias or URL path of the row
     */
    public static function row( string $kind, int|string $id, string $path ): self
    {
        return new self( new UrlKey( Kind::key( $kind, $id ) ), $id, $path );
    }


    /**
     * Returns the claim of a translated article, category or menu item in the domain of its language.
     *
     * @param Kind::ARTICLE|Kind::CATEGORY|Kind::MENU $kind Type of the translated row
     * @param int|string $id ID of the translated row
     * @param string $path URL path of the translation
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     */
    public static function translation( string $kind, int|string $id, string $path, string $domain ): self
    {
        return new self( new UrlKey( Kind::translationKey( $kind, $id ), $domain ), $id, $path );
    }
}
