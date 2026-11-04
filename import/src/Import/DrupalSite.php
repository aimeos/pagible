<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Resolves the URLs of the files and pages of the Drupal site from the --file-base URL.
 */
class DrupalSite
{
    /**
     * Initializes the object.
     *
     * @param string $fileBase Base URL of the Drupal files without trailing slash
     * @param string $domain Domain of the imported pages
     * @param array<int, string> $domains Domains of the languages with their own domain
     */
    public function __construct(
        protected string $fileBase,
        protected string $domain,
        protected array $domains = []
    ) {
    }


    /**
     * Returns the base URL of the Drupal site including the path of subdirectory installations.
     *
     * The site path is the part of the --file-base URL before "/sites/", e.g. "https://example.com/drupal"
     * for "https://example.com/drupal/sites/default/files". Without "/sites/", the origin is used.
     */
    public function base(): string
    {
        if( !( $origin = $this->origin() ) ) {
            return '';
        }

        $path = (string) parse_url( $this->fileBase, PHP_URL_PATH );
        $pos = strpos( $path, '/sites/' );

        return $origin . ( $pos !== false ? substr( $path, 0, $pos ) : '' );
    }


    /**
     * Returns the URL of a file path relative to the --file-base URL.
     */
    public function file( string $path ): string
    {
        return Files::url( $path, $this->fileBase );
    }


    /**
     * Tests if the host name is the one of the --file-base URL, the imported pages or a language.
     *
     * Host names with and without "www." are the same site.
     *
     * @param string $host Host name, optionally with port
     * @param string|null $domain Domain of the content to test only for that domain, empty string for the imported pages or NULL for all domains
     */
    public function host( string $host, ?string $domain = null ): bool
    {
        $strip = fn( string $name ) => (string) preg_replace( '/^www\./', '', strtolower( (string) preg_replace( '/:\d+$/', '', $name ) ) );
        $hosts = match( true ) {
            $domain === null => [parse_url( $this->fileBase, PHP_URL_HOST ) ?: '', $this->domain, ...$this->domains],
            $domain === '' => [parse_url( $this->fileBase, PHP_URL_HOST ) ?: '', $this->domain],
            default => [$domain],
        };

        return in_array( $strip( $host ), array_map( $strip, array_filter( $hosts ) ), true );
    }


    /**
     * Resolves the src of an inline image to a full URL.
     */
    public function image( string $src ): string
    {
        $src = $this->original( $src );

        if( str_starts_with( $src, '//' ) ) {
            $src = ( parse_url( $this->fileBase, PHP_URL_SCHEME ) ?: 'https' ) . ':' . $src;
        }

        // URLs of the Drupal site with another scheme or host name must refer to the same file
        if( preg_match( '#^https?://([^/?\#]+)(.*)$#is', $src, $m ) && $this->host( $m[1] ) && ( $origin = $this->origin() ) ) {
            return $origin . ( str_starts_with( $m[2], '/' ) ? $m[2] : '/' . $m[2] );
        }

        // root-relative paths already contain the path of subdirectory installations
        if( str_starts_with( $src, '/' ) && ( $origin = $this->origin() ) ) {
            return $origin . $src;
        }

        return $this->file( $src );
    }


    /**
     * Returns the origin (scheme, host and port) of the --file-base URL.
     */
    public function origin(): string
    {
        $parts = parse_url( $this->fileBase ) ?: [];

        if( empty( $parts['host'] ) ) {
            return '';
        }

        return ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
    }


    /**
     * Returns the URL of the original image instead of an image style derivative.
     *
     * Derivatives are stored in "styles/<style>/<scheme>/<path>" of the files directory, e.g.
     * "/sites/default/files/styles/large/public/photo.jpg?itok=abc" for "/sites/default/files/photo.jpg"
     * and "/system/files/styles/large/private/doc.png" for "/system/files/doc.png".
     */
    protected function original( string $src ): string
    {
        if( !preg_match( '/\/styles\/[^\/]+\/(?:public|private)\//', $src ) ) {
            return $src;
        }

        $src = (string) preg_replace( '/\/styles\/[^\/]+\/(?:public|private)\//', '/', $src, 1 );
        $src = (string) preg_replace( '/([?&])itok=[^&#]*&?/', '$1', $src );

        return (string) preg_replace( '/[?&](?=#|$)/', '', $src );
    }
}
