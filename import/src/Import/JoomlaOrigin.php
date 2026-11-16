<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Recognizes the URLs of the Joomla site by its hosts, the script name and the URL suffix.
 */
class JoomlaOrigin
{
    /** Name of the script of the Joomla URLs */
    public const SCRIPT = 'index.php';
    /** Suffix Joomla adds to the URL paths if enabled */
    protected const SUFFIX_PATTERN = '#\.html$#i';

    /** Absolute URL with optional HTTP scheme, the host as first and the rest as second group */
    protected const ABSOLUTE_PATTERN = '#^(?:https?:)?//([^/?\#]*)(.*)$#is';

    /** Scheme and slashes at the start of an absolute URL */
    protected const SCHEME_PATTERN = '#^[a-z][a-z0-9+.-]*://#';

    /** User info before the host of an absolute URL */
    protected const USER_PATTERN = '#^.*@#';

    /** Host and optional port, the host as first and the port as second group */
    protected const AUTHORITY_PATTERN = '#^(.*?)(?::(\d*))?$#';

    /** Ports of HTTP and HTTPS which aren't part of the normalized hosts */
    protected const DEFAULT_PORTS = ['', '80', '443'];

    /**
     * Base URL without scheme which starts with a host name like "cdn.example.org/images", "localhost:8080" or an
     * IPv4 address and an optional port. Host names need a top level domain of letters, so local paths like
     * "backup.v2/images" aren't hosts.
     */
    protected const SCHEMELESS_PATTERN = '#^(?:localhost|\d{1,3}(?:\.\d{1,3}){3}|(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63})(?::\d+)?(?:/|$)#i';

    /** Extensions of backup and temporary directories like "joomla.bak" which aren't top level domains */
    protected const LOCAL_EXTENSIONS = ['bak', 'backup', 'old', 'orig', 'tmp', 'temp'];

    /** @var string Lower case host of the base URL with non-default port or an empty string if there's none */
    protected string $host;

    /** @var string Path of the base URL without trailing slash */
    protected string $path;

    /** @var string Lower case domain name of the imported pages with non-default port */
    protected string $domainHost;

    /** @var array<int, string> Lower case hosts of the base URL and the domain with non-default ports, without empty ones */
    protected array $hosts;

    /** Regular expression matching the path of the base URL at the start of URL paths or NULL if there's no path */
    protected ?string $basePattern;


    /**
     * Initializes the object.
     *
     * @param string $domain Domain name of the imported pages with optional port like "example.com:8080"
     * @param string $fileBase Base URL of the Joomla site, base URLs without scheme like "example.com/joomla" use HTTPS
     * @param bool $suffix If Joomla adds ".html" to the URLs
     */
    public function __construct(
        public readonly string $domain = '',
        string $fileBase = '',
        private readonly bool $suffix = false
    ) {
        $parts = parse_url( self::baseUrl( $fileBase ) ) ?: [];
        $this->host = isset( $parts['host'] ) ? self::host( $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) ) : '';
        $this->path = rtrim( $parts['path'] ?? '', '/' );
        $this->domainHost = self::host( $domain );
        $this->hosts = array_values( array_filter( [$this->host, $this->domainHost] ) );
        // URL paths are case-sensitive, so the directory of the site is too
        $this->basePattern = $this->path !== '' ? '#^' . preg_quote( $this->path, '#' ) . '(?=[/?\#]|$)#' : null;
    }


    /**
     * Returns the base URL of the files with HTTPS as scheme if it has none but starts with a host name.
     *
     * Values which are existing local paths or whose first segment ends in an extension of backup directories
     * like "joomla.bak/images" are local paths.
     *
     * @param string $fileBase Base URL like "https://example.com/joomla", "example.com/joomla" or a local path
     * @return string Base URL like "https://example.com/joomla" or the unchanged value if it has a scheme or no host
     */
    public static function baseUrl( string $fileBase ): string
    {
        if( !preg_match( self::SCHEMELESS_PATTERN, $fileBase ) || file_exists( $fileBase ) ) {
            return $fileBase;
        }

        $host = (string) preg_replace( '#[:/].*$#s', '', $fileBase );
        $ext = strtolower( (string) pathinfo( $host, PATHINFO_EXTENSION ) );

        return in_array( $ext, self::LOCAL_EXTENSIONS, true ) ? $fileBase : 'https://' . $fileBase;
    }


    /**
     * Returns the regular expression fragment of the Joomla URLs routed by the script with optional leading slash.
     *
     * Matched are the script with query string like "/index.php?Itemid=3", the query string relative to the script
     * like "?Itemid=3" or "/?Itemid=3" and the script without query string like "index.php", which must be followed
     * by the end of the subject or one of the characters ending the URL. The query string follows the fragment,
     * it can be used with "#" and "/" as delimiters.
     *
     * @param string $end Regular expression characters ending the URL after the script without query string, e.g. "\#" or "\#\"'"
     */
    public static function indexUrlFragment( string $end = '\#' ): string
    {
        return '\/?(?:' . self::scriptFragment() . '(?:\?|(?=[' . $end . ']|$))|\?)';
    }


    /**
     * Returns the hosts and paths of the Joomla site from the base URL of the files and the domain.
     *
     * @return array<int, string> Hosts with optional path like "example.com/joomla"
     */
    public function sites(): array
    {
        $sites = $this->host !== '' ? [$this->host . $this->path] : [];

        return array_values( array_unique( array_filter( [...$sites, $this->domainHost] ) ) );
    }


    /**
     * Tests if the absolute URL is one of the Joomla site.
     *
     * Unlike local(), which keeps all URLs if the hosts are unknown, absolute URLs are external then.
     *
     * @param string $url Absolute URL like "https://example.com/page" or "//example.com/page"
     */
    public function internal( string $url ): bool
    {
        return $this->hosts !== [] && $this->local( $url ) !== null;
    }


    /**
     * Returns the source of a file of the Joomla site relative to the base URL of the files.
     *
     * The files are fetched from the base URL, which contains the directory of Joomla sites in subdirectories
     * already. Root-relative URL paths in the HTML start with the directory, so it's removed. Other root-relative
     * paths like the ones of JoomlaHtmlUrls::absolute() are relative to the base URL like relative paths.
     * Absolute URLs of the files below the base URL are relative to it too, so the same file has the same source
     * however it's referenced. Other absolute URLs are returned unchanged, scheme-relative ones use HTTPS.
     * Query strings and fragments are kept, so the base URL itself with a query like "https://example.com/joomla?x=1"
     * is "/?x=1".
     *
     * @param string $src Decoded source like "/joomla/images/photo.jpg", "images/photo.jpg" or "https://cdn.example.com/photo.jpg"
     * @return string Source like "/images/photo.jpg" or the absolute URL
     */
    public function fileSource( string $src ): string
    {
        if( preg_match( self::ABSOLUTE_PATTERN, $src, $match ) )
        {
            // only the files below the base URL are fetched relative to it
            if( $this->host === '' || self::host( $match[1] ) !== $this->host ) {
                return JoomlaUri::schemed( $src );
            }

            $path = '/' . ltrim( $match[2], '/' );
            return $this->path === '' ? $path : $this->unbased( $path ) ?? JoomlaUri::schemed( $src );
        }

        return str_starts_with( $src, '/' ) ? $this->unbased( $src ) ?? $src : $src;
    }


    /**
     * Returns the URL path of the old URL of a Joomla redirect.
     *
     * Without domain and base URL, absolute URLs are assumed to be ones of the Joomla site like local() does.
     *
     * @param string $url Old URL like "https://example.com/old-page", "index.php/old-page" or "old-page.html"
     * @return string|null URL path without leading slash or NULL if it's no page URL, e.g. with query parameters or of another domain
     */
    public function redirectPath( string $url ): ?string
    {
        $local = $this->local( trim( $url ) );
        $parts = $local !== null ? parse_url( $local ) : false;

        if( $parts === false || isset( $parts['query'] ) ) {
            return null;
        }

        $path = $this->unsuffixed( trim( $this->unscripted( JoomlaUri::decodedPath( $parts['path'] ?? null ) ), '/' ) );

        return $path !== '' ? $path : null;
    }


    /**
     * Returns the URL path of a URL of the Joomla site like the request URI, which starts with the directory of the site.
     *
     * Joomla sites in subdirectories see their URLs relative to the host, e.g. "/joomla/old-page" for "old-page" or
     * "/old-page" if the site is in the "joomla" directory. URL paths already starting with the directory are kept.
     * Without host in the base URL of the files, the directory of the site isn't known, so the path is kept.
     *
     * @param string $path URL path relative to the site or the host, e.g. "old-page", "/old-page" or "/joomla/old-page"
     * @return string URL path relative to the host like "/joomla/old-page" or the path itself if the site has no directory
     */
    public function requestPath( string $path ): string
    {
        if( $this->host === '' || $this->path === '' || str_starts_with( $path, '/' ) && $this->unbased( $path ) !== null ) {
            return $path;
        }

        return $this->path . '/' . ltrim( $path, '/' );
    }


    /**
     * Removes the script name Joomla adds to the URL paths without URL rewriting, e.g. "index.php/page".
     */
    public function unscripted( string $path ): string
    {
        // the script name at the start of the URL path, e.g. "index.php/page"
        return preg_replace( '#^/?' . self::scriptFragment() . '(/|$)#i', '', $path ) ?? $path;
    }


    /**
     * Returns the URL path of a link target on the Joomla site like the imported pages use it.
     *
     * The new URLs of the redirects are Joomla URLs with the script name and the suffix if enabled. The script name
     * is removed always like from the old URLs of the redirects, see redirectPath(), because the imported pages don't
     * use it. The suffix is only removed with the suffix option, without it the URL paths are imported 1:1.
     *
     * @param string $path URL path without leading slash, query string and fragment, e.g. "index.php/page.html"
     * @return string URL path without script name and suffix, e.g. "page"
     */
    public function targetPath( string $path ): string
    {
        return $this->unsuffixed( $this->unscripted( $path ) );
    }


    /**
     * Removes the ".html" suffix Joomla adds to the URL paths if enabled.
     */
    protected function unsuffixed( string $path ): string
    {
        if( !$this->suffix ) {
            return $path;
        }

        return preg_replace( self::SUFFIX_PATTERN, '', $path ) ?? $path;
    }


    /**
     * Returns the URL without scheme and host if it's an absolute URL of the Joomla site.
     *
     * The Joomla site uses the host and the path of the base URL of the files and the domain. The path of
     * the base URL of Joomla sites in subdirectories is removed from absolute URLs and URL paths. If both
     * hosts are unknown, all absolute URLs are kept.
     *
     * @param string $url URL like "https://example.com/page", "//example.com/page", "/joomla/page" or "page"
     * @return string|null URL path like "/page", the URL itself if it's no absolute URL or the hosts are unknown
     *  and NULL if it's an absolute URL of another site
     */
    public function local( string $url ): ?string
    {
        if( !preg_match( self::ABSOLUTE_PATTERN, $url, $match ) ) {
            // URL paths of Joomla sites in subdirectories start with the directory
            return str_starts_with( $url, '/' ) ? $this->unbased( $url ) ?? $url : $url;
        }

        if( $this->hosts === [] ) {
            return $url;
        }

        $host = self::host( $match[1] );
        $path = '/' . ltrim( $match[2], '/' );

        // URLs of the host outside of the directory of the Joomla site are only local if the host is the domain too
        if( $host === $this->host && $this->path !== '' ) {
            return $this->unbased( $path ) ?? ( $host === $this->domainHost ? $path : null );
        }

        return in_array( $host, $this->hosts, true ) ? $path : null;
    }


    /**
     * Returns the normalized host of a URL, a domain or the authority of an absolute URL like the hosts are compared.
     *
     * Scheme, user info, path, query and fragment aren't part of the host, the ports of HTTP and HTTPS are removed.
     *
     * @param string $url URL, domain or authority with optional scheme, user info, port and path like "https://user@Example.com:443/"
     * @return string Lower case host with non-default port like "example.com" or "example.com:8080"
     */
    public static function host( string $url ): string
    {
        $host = strtolower( trim( $url ) );
        $host = preg_replace( [self::SCHEME_PATTERN, '#[/?\#].*$#s', self::USER_PATTERN], '', $host ) ?? $host;

        if( !preg_match( self::AUTHORITY_PATTERN, $host, $match ) ) {
            return $host;
        }

        $port = $match[2] ?? '';
        return in_array( $port, self::DEFAULT_PORTS, true ) ? $match[1] : $match[1] . ':' . $port;
    }


    /**
     * Returns the regular expression fragment of the script name, usable with "#" and "/" as delimiters.
     */
    protected static function scriptFragment(): string
    {
        return preg_quote( self::SCRIPT, '#' );
    }


    /**
     * Removes the path of the base URL of Joomla sites in subdirectories from the URL path.
     *
     * @param string $path URL path with leading slash like "/joomla/page"
     * @return string|null URL path without the directory like "/page" or NULL if it's outside the directory or there's none
     */
    protected function unbased( string $path ): ?string
    {
        if( $this->basePattern === null ) {
            return null;
        }

        $count = 0;
        $sub = preg_replace( $this->basePattern, '', $path, 1, $count ) ?? $path;

        return $count > 0 ? '/' . ltrim( $sub, '/' ) : null;
    }
}
