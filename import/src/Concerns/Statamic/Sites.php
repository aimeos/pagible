<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Import\Statamic\Reader;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\DB;


/**
 * Reads the Statamic sites and their languages, domains and URL path prefixes.
 */
trait Sites
{
    /** @var array<string, array{lang: string, url: string}> Language and URL of the Statamic sites by handle if they are stored in the database */
    protected array $sites = [];
    /** @var array<string, string> URL path prefixes of the translated sites like "de" by site handle */
    protected array $prefixes = [];


    /**
     * Reads the imported site and its language from the options or the sites stored in the database.
     *
     * @return bool TRUE if the site exists, FALSE if not
     */
    protected function siteOptions(): bool
    {
        $sites = Reader::sites( DB::connection( $this->connection ) );
        $this->sites = $sites ?? [];
        $this->site = $this->stringOption( 'site' ) ?: ( (string) array_key_first( $this->sites ) ?: 'default' );

        if( $this->sites !== [] && !isset( $this->sites[$this->site] ) )
        {
            $this->error( "Statamic site \"{$this->site}\" doesn't exist, available sites: " . implode( ', ', array_keys( $this->sites ) ) );
            return false;
        }

        // the language of the site is used if no language is given
        if( $this->lang === '' ) {
            $lang = $this->sites[$this->site]['lang'] ?? '';
            $this->lang = $lang !== '' && Utils::isValidLang( $lang ) ? $lang : 'en';
        }

        return true;
    }


    /**
     * Returns the --language option value with the language and domain of the site stored in the database.
     *
     * Values like "de" only containing the site handle are completed by the language of the site and its
     * URL if the site is on another domain than the imported site.
     *
     * @param mixed $value Option value like "site", "site:lang" or "site:lang:url"
     * @return mixed Option value like "site:lang" or "site:lang:url" or the unchanged value
     */
    protected function siteLanguage( mixed $value ): mixed
    {
        if( !is_string( $value ) || str_contains( $value, ':' ) || !isset( $this->sites[$value = trim( $value )] ) || ( $lang = $this->sites[$value]['lang'] ) === '' ) {
            return $value;
        }

        $url = (string) preg_replace( '#^//#', 'https://', $this->sites[$value]['url'] );
        $host = is_string( $host = parse_url( $url, PHP_URL_HOST ) ) && preg_match( '#^https?://#i', $url ) && !str_contains( $url, '{{' ) ? $host : null;
        $own = parse_url( $this->sites[$this->site]['url'] ?? '', PHP_URL_HOST );
        $authority = strtok( (string) preg_replace( '#^(?:[a-z][a-z0-9+.-]*:)?//#i', '', $url ), '/' );

        // Antlers tags like "{{ env:DE_HOST }}" in the host can't be resolved, only the app URL is known
        if( is_string( $authority ) && str_contains( $authority, '{{' ) && !preg_match( '/^\{\{\s*(?:config:app:url|env:APP_URL|site:url)\s*\}\}$/', $authority ) ) {
            $this->warnOnce( "site-host:{$value}", "The host of the \"{$value}\" site URL \"{$url}\" contains Antlers tags, so its translations are imported for the domain of the imported site. Use --language={$value}:{$lang}:https://… if the site is on another domain" );
        }

        return $value . ':' . $lang . ( $host !== null && $host !== $own ? ':' . $url : '' );
    }


    /**
     * Returns the URL path prefix of a Statamic site from its URL.
     *
     * Antlers tags like "{{ config:app:url }}" in the URL are removed, e.g. "{{ config:app:url }}/de/" is "de".
     *
     * @param string $url Absolute or relative URL of the site like "https://example.com/de/" or "/de/"
     * @return string URL path without slashes, empty if the site has no path
     */
    protected function prefix( string $url ): string
    {
        $url = trim( (string) preg_replace( '/\{\{.*?\}\}/s', '', $url ) );
        $url = (string) preg_replace( '~^(?:[a-z][a-z0-9+.-]*:)?//[^/?#]*~i', '', $url );
        $path = strtok( $url, '?#' );

        return is_string( $path ) ? trim( $path, '/' ) : '';
    }
}
