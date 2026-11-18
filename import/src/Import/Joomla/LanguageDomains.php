<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Returns the domains and home page URLs of the Joomla languages.
 *
 * Languages on the domain of the imported pages share its URL paths and use the language code as path of their
 * home page. Languages on own domains have separate URL paths and use the root of their domain.
 */
class LanguageDomains
{
    /** Normalized host of the domain of the imported pages, see Origin::host() */
    protected string $host;


    /**
     * Initializes the object.
     *
     * @param Languages $languages Imported languages with their URL paths and domains
     * @param Origin $origin Domain of the imported pages
     */
    public function __construct( protected Languages $languages, protected Origin $origin )
    {
        $this->host = Origin::host( $origin->domain );
    }


    /**
     * Returns the URL of the home page of the Joomla language.
     *
     * @return string|null URL path, absolute URL for languages with own domains or NULL for unknown languages
     */
    public function homeUrl( string $tag ): ?string
    {
        $language = $this->languages->language( $tag );
        return $language !== null ? $this->url( $this->rootPath( $language ), $this->ownDomain( $language ) ) : null;
    }


    /**
     * Returns the URL path of the root page variant of the language.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the language
     * @return string URL path without leading slash, an empty string for languages on own domains
     */
    public function rootPath( array $language ): string
    {
        return $this->ownDomain( $language ) !== '' ? '' : $language['lang'];
    }


    /**
     * Returns the URL path of the root page variant of the Joomla language.
     *
     * @return string|null URL path without leading slash, an empty string for languages on own domains or NULL for unknown languages
     */
    public function tagRootPath( string $tag ): ?string
    {
        $language = $this->languages->language( $tag );
        return $language !== null ? $this->rootPath( $language ) : null;
    }


    /**
     * Returns the domain of the Joomla language whose URL paths are separate from the ones of the imported pages.
     *
     * @return string Domain or empty string if the language uses the domain of the imported pages or is unknown
     */
    public function domain( string $tag ): string
    {
        $language = $this->languages->language( $tag );
        return $language !== null ? $this->ownDomain( $language ) : '';
    }


    /**
     * Returns the URL of the URL path, the absolute URL if the path belongs to another domain.
     *
     * @param string $path URL path without leading slash
     * @param string $domain Domain of the language from domain() or empty string for the domain of the imported pages
     */
    public function url( string $path, string $domain ): string
    {
        return ( $domain !== '' ? 'https://' . $domain : '' ) . '/' . $path;
    }


    /**
     * Returns the domain of the language if it isn't the domain of the imported pages.
     *
     * Host names are case insensitive, so the domains are compared and returned normalized like Origin does: in lower
     * case and without whitespace, scheme, path and default port, e.g. " https://Example.com:443/ " is "example.com".
     * The domains of the languages are host names already, see Variants::language(), so they only become lower case.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the language
     * @return string Normalized domain or empty string if it's the domain of the imported pages
     */
    protected function ownDomain( array $language ): string
    {
        $host = Origin::host( $language['domain'] );
        return $host === $this->host ? '' : $host;
    }
}
