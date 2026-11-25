<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Utils;


/**
 * Maps the TYPO3 page trees to the domains of the imported pages and returns the base URL of the TYPO3 files.
 */
class Site
{
    /** @var array<int, string> Domains by UID of the TYPO3 root page */
    protected array $domains = [];

    /** Domain of the page trees without own domain */
    protected string $domain = '';

    /** @var array<int, array{lang: string, domain: string}> Languages of the site configurations by TYPO3 language UID */
    protected array $languages = [];

    /** @var array<int, string> Fallback types of the languages of the site configurations by TYPO3 language UID */
    protected array $fallbacks = [];

    /** @var array<int, array{languages: array<int, array{lang: string, domain: string}>, fallbacks: array<int, string>, chains: array<int, list<int>>, default: string}> Languages of the site configurations by UID of the root page */
    protected array $sites = [];


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
     * Reads the site configurations of the TYPO3 installation, e.g. "config/sites/main/config.yaml".
     *
     * The domains of the site configurations are used for the page trees without domain from the
     * --domain options or the sys_domain table, which is only used by TYPO3 versions before 10.
     *
     * @param string $path Path to the TYPO3 installation
     * @return int Number of site configurations found
     * @throws \RuntimeException If the Symfony YAML component isn't installed
     */
    public function configure( string $path ): int
    {
        $files = glob( rtrim( $path, '/' ) . '/config/sites/*/config.yaml' ) ?: [];
        $files = $files ?: ( glob( rtrim( $path, '/' ) . '/typo3conf/sites/*/config.yaml' ) ?: [] );

        if( $files && !class_exists( \Symfony\Component\Yaml\Yaml::class ) ) {
            throw new \RuntimeException( 'Reading the TYPO3 site configurations requires "symfony/yaml", install it using "composer require symfony/yaml"' );
        }

        foreach( $files as $file )
        {
            $config = \Symfony\Component\Yaml\Yaml::parseFile( $file );

            if( !is_array( $config ) || !( $root = (int) ( $config['rootPageId'] ?? 0 ) ) ) {
                continue;
            }

            $host = self::host( (string) ( $config['base'] ?? '' ) ) ?: self::variant( (array) ( $config['baseVariants'] ?? [] ) );
            $site = ['languages' => [], 'fallbacks' => [], 'chains' => [], 'default' => ''];

            if( $host !== '' && $this->domain === '' ) {
                $this->domains[$root] ??= $host;
            }

            foreach( (array) ( $config['languages'] ?? [] ) as $language )
            {
                if( !is_array( $language ) || ( $language['enabled'] ?? true ) === false ) {
                    continue;
                }

                $uid = (int) ( $language['languageId'] ?? 0 );
                $code = self::code( $language );

                // the default language of the site is the language of the pages if no --lang option is given
                if( $uid === 0 )
                {
                    $site['default'] = $code;
                    continue;
                }

                $domain = self::host( (string) ( $language['base'] ?? '' ) ) ?: self::variant( (array) ( $language['baseVariants'] ?? [] ) );
                $domain = $domain !== $host ? $domain : '';

                // languages with the same code on the same domain, e.g. "de_DE" and "de_CH", use the region for all but the first one
                $used = array_column( array_filter( $site['languages'], fn( $entry ) => $entry['domain'] === $domain ), 'lang' );

                if( $code !== '' && ( $domain === '' && $site['default'] === $code || in_array( $code, $used, true ) ) ) {
                    $code = self::regional( $language ) ?: $code;
                }

                if( $code !== '' ) {
                    $site['languages'][$uid] = ['lang' => $code, 'domain' => $domain];
                }

                $type = (string) ( $language['fallbackType'] ?? 'strict' );
                $site['fallbacks'][$uid] = in_array( $type, ['strict', 'fallback', 'free'], true ) ? $type : 'strict';
                $site['chains'][$uid] = array_values( array_filter( array_map( 'intval', explode( ',', (string) ( $language['fallbacks'] ?? '' ) ) ), fn( $id ) => $id > 0 && $id !== $uid ) );
            }

            $this->sites[$root] = $site;
            $this->languages += $site['languages'];
            $this->fallbacks += $site['fallbacks'];
        }

        return count( $files );
    }


    /**
     * Returns the fallback type of the TYPO3 language from the site configuration of the root page or of the first site using the language.
     *
     * @param int|null $root UID of the root page of the site configuration or NULL for the first site using the language
     * @return string "strict", "fallback" or "free"
     */
    public function fallback( int $langUid, ?int $root = null ): string
    {
        return ( $root !== null ? $this->sites[$root]['fallbacks'][$langUid] ?? null : null ) ?? $this->fallbacks[$langUid] ?? 'strict';
    }


    /**
     * Returns the UIDs of the languages used if a page isn't translated into the language, without the default language.
     *
     * @param int|null $root UID of the root page of the site configuration or NULL for the first site using the language
     * @return list<int> Language UIDs in their order
     */
    public function chain( int $langUid, ?int $root = null ): array
    {
        if( $root !== null && isset( $this->sites[$root]['chains'][$langUid] ) ) {
            return $this->sites[$root]['chains'][$langUid];
        }

        foreach( $this->sites as $site )
        {
            if( isset( $site['chains'][$langUid] ) ) {
                return $site['chains'][$langUid];
            }
        }

        return [];
    }


    /**
     * Tests if a site configuration exists for the TYPO3 root page.
     */
    public function configured( int $root ): bool
    {
        return isset( $this->sites[$root] );
    }


    /**
     * Returns the code of the default language of the site configuration or an empty string if unknown.
     *
     * @param int|null $root UID of the root page of the site configuration
     */
    public function defaultLang( ?int $root ): string
    {
        return $root !== null ? $this->sites[$root]['default'] ?? '' : '';
    }


    /**
     * Tests if a domain is configured for the TYPO3 root page.
     */
    public function has( object $root ): bool
    {
        return isset( $this->domains[(int) ( $root->uid ?? 0 )] );
    }


    /**
     * Returns the languages of the site configurations by TYPO3 language UID.
     *
     * TYPO3 language UIDs are configured per site, so the same UID can be another language in another site.
     *
     * @param int|null $root UID of the root page of the site configuration or NULL for the languages of all sites, the first site wins
     * @return array<int, array{lang: string, domain: string}>
     */
    public function languages( ?int $root = null ): array
    {
        return $root !== null ? $this->sites[$root]['languages'] ?? [] : $this->languages;
    }


    /**
     * Returns the languages of each site configuration by UID of the root page and TYPO3 language UID.
     *
     * @return array<int, array<int, array{lang: string, domain: string}>>
     */
    public function sites(): array
    {
        return array_map( fn( $site ) => $site['languages'], $this->sites );
    }


    /**
     * Returns the language code of a site language, e.g. "de" for the locale "de_DE.UTF-8", or an empty string if it's invalid.
     *
     * @param array<mixed> $language Language of the site configuration
     */
    protected static function code( array $language ): string
    {
        $code = trim( (string) ( $language['iso-639-1'] ?? $language['twoLetterIsoCode'] ?? '' ) )
            ?: (string) preg_replace( '/[_.@-].*$/s', '', trim( (string) ( $language['locale'] ?? '' ) ) );
        $code = strtolower( $code );

        return Utils::isValidLang( $code ) ? $code : '';
    }


    /**
     * Returns the language code including the region of the TYPO3 site language, e.g. "de-CH", or an empty string.
     *
     * @param array<string, mixed> $language Language configuration
     */
    protected static function regional( array $language ): string
    {
        $code = trim( (string) ( $language['hreflang'] ?? '' ) )
            ?: str_replace( '_', '-', (string) preg_replace( '/[.@].*$/s', '', trim( (string) ( $language['locale'] ?? '' ) ) ) );
        $parts = explode( '-', $code, 2 );
        $code = strtolower( $parts[0] ) . ( isset( $parts[1] ) ? '-' . strtoupper( $parts[1] ) : '' );

        return str_contains( $code, '-' ) && Utils::isValidLang( $code ) ? $code : '';
    }


    /**
     * Returns the host of the base variants, the one for the production context or the first one with a host.
     *
     * @param array<mixed> $variants Base variants with "base" and "condition" entries
     */
    protected static function variant( array $variants ): string
    {
        $first = '';

        foreach( $variants as $variant )
        {
            if( !is_array( $variant ) || ( $host = self::host( (string) ( $variant['base'] ?? '' ) ) ) === '' ) {
                continue;
            }

            if( preg_match( '/applicationContext\s*(?:==|matches)\s*["\']\^?Production/i', (string) ( $variant['condition'] ?? '' ) ) ) {
                return $host;
            }

            $first = $first ?: $host;
        }

        return $first;
    }


    /**
     * Returns the host of a site or language base like "https://example.com/en/", or an empty string for relative bases like "/en/".
     */
    protected static function host( string $base ): string
    {
        // environment variables like "%env(BASE_DOMAIN)%" can't be resolved
        if( str_contains( $base, '%env(' ) ) {
            return '';
        }

        $host = parse_url( str_starts_with( $base, '//' ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $base ) ? $base : '//' . $base, PHP_URL_HOST );

        return is_string( $host ) && str_contains( $host, '.' ) || $host === 'localhost' ? strtolower( (string) $host ) : '';
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
