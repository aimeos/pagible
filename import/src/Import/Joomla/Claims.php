<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Claims the URL paths within the language domains while the URL paths of the pages are assigned.
 *
 * Pagible uses a URL path within the domain only once, so later rows with the same path get their ID appended.
 * The paths are compared case- and accent-insensitively because the unique index of the URL paths of the pages is
 * case- and accent-insensitive in databases with collations like utf8mb4_unicode_ci of MySQL, while the claimed paths
 * keep their case and accents, e.g. the ones of the redirects. The object is only used during the assignment, afterwards only the URL paths from slugs() are kept.
 */
class Claims
{
    /** @var array<string, array<string, true>> Lower case URL paths without accents already used by language domain */
    protected array $claimed = [];
    /** @var array<string, array<string, string>> URL paths which differ from the aliases by language domain and record key */
    protected array $slugs = [];
    /** @var array<string, string> URL paths Joomla uses or aliases of the claimed rows by record key, e.g. "a5" or "ta7" */
    protected array $paths = [];


    /**
     * Assigns an URL path in the language domain which isn't used by another article, category or menu item.
     *
     * Joomla allows the same alias in different categories, Pagible uses the URL path within the domain
     * only once, so the ID is appended to the alias of the later rows, e.g. "about-5".
     *
     * @param UrlClaim $claim Row with its alias or URL path and its language domain
     */
    public function claim( UrlClaim $claim ): void
    {
        $this->paths[$claim->key] = $slug = $claim->path;

        for( $i = 1; !$this->claimPath( $slug, $claim->domain ); $i++ ) {
            $slug = $claim->path . '-' . $claim->id . ( $i > 1 ? '-' . $i : '' );
        }

        if( $slug !== $claim->path ) {
            $this->slugs[$claim->domain][$claim->key] = $slug;
        }
    }


    /**
     * Claims the URL path in the language domain if it's not used yet, also not with different case or accents.
     *
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     * @return bool TRUE if the URL path was claimed, FALSE if it's already used
     */
    public function claimPath( string $path, string $domain = '' ): bool
    {
        $key = $this->key( $path );

        if( isset( $this->claimed[$domain][$key] ) ) {
            return false;
        }

        $this->claimed[$domain][$key] = true;
        return true;
    }


    /**
     * Returns the URL paths Joomla uses or the aliases of the claimed rows before they were made unique.
     *
     * @return array<string, string> URL paths by record key from Kind::key() or Kind::translationKey(), e.g. "a5" or "ta7"
     */
    public function paths(): array
    {
        return $this->paths;
    }


    /**
     * Returns the URL paths of the rows which differ from their aliases.
     *
     * @return array<string, array<string, string>> URL paths by language domain, an empty string for the domain
     *  of the imported pages, and record key from Kind::key() or Kind::translationKey(), e.g. "a5" or "ta7"
     */
    public function slugs(): array
    {
        return $this->slugs;
    }


    /**
     * Returns the key the URL path is compared by.
     *
     * Paths differing only in case or accents are the same path in databases with case- and accent-insensitive collation.
     * Without the "intl" extension, only the case is ignored.
     */
    protected function key( string $path ): string
    {
        $key = mb_strtolower( $path );

        if( class_exists( \Normalizer::class ) && ( $decomposed = \Normalizer::normalize( $key, \Normalizer::FORM_D ) ) !== false ) {
            $key = (string) preg_replace( '/\p{Mn}+/u', '', $decomposed );
        }

        return $key;
    }
}
