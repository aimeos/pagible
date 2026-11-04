<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Maps the TYPO3 page trees to the domains of the imported pages and returns the base URL of the TYPO3 files.
 */
class Typo3Site
{
    /** @var array<int, string> Domains by UID of the TYPO3 root page */
    protected array $domains = [];

    /** Domain of the page trees without own domain */
    protected string $domain = '';


    /**
     * Initializes the domains from the --domain options and the TYPO3 domain records.
     *
     * Plain domains (e.g. "example.com") are used as default, domains with root page UID
     * (e.g. "example.com:1") for the page tree of that root page. The domain records are only
     * used if there's no default domain.
     *
     * @param array<mixed> $options Values of the --domain option
     * @param \Closure(): array<int, string> $domains Returns the domains of the sys_domain table by root page UID
     */
    public function __construct( array $options, \Closure $domains )
    {
        foreach( $options as $entry )
        {
            $parts = explode( ':', (string) $entry, 2 );

            if( !empty( $parts[1] ) ) {
                $this->domains[(int) $parts[1]] = $parts[0];
            } else {
                $this->domain = $parts[0];
            }
        }

        if( $this->domain === '' ) {
            $this->domains += $domains();
        }
    }


    /**
     * Returns the domain of the page tree or the default domain.
     *
     * @param object|null $root TYPO3 root page of the page tree or NULL for the default domain
     */
    public function domain( ?object $root = null ): string
    {
        return $this->domains[(int) ( $root->uid ?? 0 )] ?? $this->domain;
    }


    /**
     * Returns the base URL of the TYPO3 files, by default the conventional fileadmin URL of the first domain.
     *
     * @param string $base Base URL from the --file-base option or empty string
     */
    public function fileBase( string $base ): string
    {
        if( $base !== '' ) {
            return $base;
        }

        if( ( $host = $this->domain ?: (string) reset( $this->domains ) ) === '' ) {
            return '';
        }

        return rtrim( str_starts_with( $host, 'http' ) ? $host : 'https://' . $host, '/' ) . '/fileadmin';
    }
}
