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
     * @param string $key Record key of the row from Kind::key() or Kind::translationKey(), e.g. "a5" or "ta7"
     * @param int|string $id ID of the row appended to the URL path if it's used already
     * @param string $path Alias or URL path of the row
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     */
    private function __construct(
        public string $key,
        public int|string $id,
        public string $path,
        public string $domain
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
        return new self( Kind::key( $kind, $id ), $id, $path, '' );
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
        return new self( Kind::translationKey( $kind, $id ), $id, $path, $domain );
    }


    /**
     * Returns the record key and the language domain of the claim.
     */
    public function urlKey(): UrlKey
    {
        return new UrlKey( $this->key, $this->domain );
    }
}
