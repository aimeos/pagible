<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Collects the redirects of the Redirect module, the front page and the node paths.
 */
class DrupalRedirects
{
    /**
     * @param DrupalReader $reader Reads the Drupal database
     * @param DrupalData $data Reads and caches the configuration of the Drupal database
     * @param DrupalLanguages $languages Languages and URL path prefixes of the Drupal language codes
     * @param DrupalUrls $urls Front page of the imported pages
     * @param Collection<int|string, mixed> $nodes Imported Drupal nodes by node ID
     */
    public function __construct(
        protected DrupalReader $reader,
        protected DrupalData $data,
        protected DrupalLanguages $languages,
        protected DrupalUrls $urls,
        protected Collection $nodes
    ) {
    }


    /**
     * Returns the redirects one by one instead of all at once.
     *
     * Redirects of languages with their own domain are returned for that domain. Redirects of the Redirect module
     * for the /node/{nid} paths of the imported nodes are skipped if the redirects of the node paths are added.
     *
     * @param \Closure(int): ?string $path Returns the URL path of the page created for the node ID or NULL if there's none
     * @param iterable<int|string> $nids IDs of the nodes pages have been created for
     * @param bool $nodes TRUE to add redirects from the /node/{nid} paths to the imported pages
     * @return \Generator<int, array{domain: string, path: string, to: string, language: array{lang: string, domain: string}|null}> Domain, source path, target and language with its own domain
     */
    public function all( \Closure $path, iterable $nids, bool $nodes ): \Generator
    {
        $created = $nodes ? array_fill_keys( is_array( $nids ) ? $nids : iterator_to_array( $nids, false ), true ) : [];
        // the front page in the default language is available at its language prefix in Drupal, e.g. "/en"
        $prefix = ( $front = $this->urls->front() ) ? $this->languages->prefix( (string) ( $this->nodes->get( $front )->langcode ?? '' ) ) : '';
        $hasPrefix = false;

        if( $this->data->hasTable( 'redirect' ) )
        {
            foreach( $this->reader->redirects() as $row )
            {
                $langcode = (string) ( $row->language ?? '' );
                $domain = ( $language = $this->languages->language( $langcode ) )['domain'] ?? '';
                $source = trim( $this->languages->prefix( $langcode ) . '/' . trim( (string) ( $row->redirect_source__path ?? '' ), '/' ), '/' );

                // the redirects of the node paths added below are used instead
                if( $domain === '' && preg_match( '/^node\/(\d+)$/', $source, $m ) && isset( $created[$m[1]] ) ) {
                    continue;
                }

                $hasPrefix = $hasPrefix || $domain === '' && $source === $prefix;

                yield ['domain' => $domain, 'path' => $source, 'to' => $this->target( (string) ( $row->redirect_redirect__uri ?? '' ), $path ),
                    'language' => $domain !== '' ? $language : null];
            }
        }

        if( $prefix !== '' && !$hasPrefix ) {
            yield ['domain' => '', 'path' => $prefix, 'to' => '/', 'language' => null];
        }

        foreach( $created as $nid => $flag ) {
            yield ['domain' => '', 'path' => 'node/' . $nid, 'to' => '/' . ltrim( (string) $path( (int) $nid ), '/' ), 'language' => null];
        }
    }


    /**
     * Returns the target of a Redirect module URI as Pagible path or external URL.
     *
     * @param string $uri Redirect URI, e.g. "entity:node/42", "internal:/about" or "https://example.com"
     * @param \Closure(int): ?string $path Returns the URL path of the page created for the node ID or NULL if there's none
     * @return string Target or empty string if it can't be resolved
     */
    public function target( string $uri, \Closure $path ): string
    {
        if( preg_match( '/^(?:entity:node\/|internal:\/node\/|route:entity\.node\.canonical;node=)(\d+)([?#].*)?(?:;|$)/', $uri, $m ) ) {
            return ( $target = $path( (int) $m[1] ) ) !== null ? '/' . ltrim( $target, '/' ) . ( $m[2] ?? '' ) : '';
        }

        if( $uri === 'internal:/<front>' || $uri === 'route:<front>' ) {
            return '/';
        }

        if( str_starts_with( $uri, 'internal:/' ) ) {
            return '/' . ltrim( substr( $uri, 10 ), '/' );
        }

        return filter_var( $uri, FILTER_VALIDATE_URL ) && preg_match( '/^https?:/i', $uri ) ? $uri : '';
    }
}
