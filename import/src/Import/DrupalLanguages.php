<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Maps the Drupal language codes to the languages, domains and URL path prefixes of the imported pages.
 */
class DrupalLanguages
{
    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration of the Drupal database
     * @param array<string, array{lang: string, domain: string}|null> $languages Languages by Drupal language code from the --language option
     */
    public function __construct( protected DrupalData $data, protected array $languages )
    {
    }


    /**
     * Returns the domains of the languages with their own domain.
     *
     * @return array<int, string>
     */
    public function domains(): array
    {
        return array_values( array_unique( array_filter( array_column( array_filter( $this->languages ), 'domain' ) ) ) );
    }


    /**
     * Returns the language of a Drupal language code.
     *
     * Codes without a --language option are used as they are if they are valid,
     * except the special codes "und" (not specified) and "zxx" (not applicable).
     *
     * @param string $langcode Drupal language code
     * @return array{lang: string, domain: string}|null Language or NULL if unknown
     */
    public function language( string $langcode ): ?array
    {
        if( !array_key_exists( $langcode, $this->languages ) )
        {
            $code = strtolower( $langcode );
            $valid = Utils::isValidLang( $code ) && !in_array( $code, ['und', 'zxx'], true );
            $this->languages[$langcode] = $valid ? Variants::language( $code ) : null;
        }

        return $this->languages[$langcode];
    }


    /**
     * Returns the URL path prefix of the language if Drupal detects the language by the URL path.
     *
     * Languages with a separate domain from the --language option don't get a prefix.
     *
     * @param string $langcode Drupal language code
     * @return string Prefix without slashes, e.g. "de", or empty string for none
     */
    public function prefix( string $langcode ): string
    {
        $url = (array) ( $this->data->config( 'language.negotiation' )['url'] ?? [] );

        if( ( $url['source'] ?? 'path_prefix' ) !== 'path_prefix' || ( $this->language( $langcode )['domain'] ?? '' ) !== '' ) {
            return '';
        }

        return trim( (string) ( ( (array) ( $url['prefixes'] ?? [] ) )[$langcode] ?? '' ), '/' );
    }
}
