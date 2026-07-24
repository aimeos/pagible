<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;


class Utils
{
    private static int $counter = 0;
    private static ?\HTMLPurifier $purifier = null;


    /**
     * Returns the editor identifier for the current user.
     *
     * @param object|null $user The authenticated user object
     * @return string The user's email or the request IP address
     */
    public static function editor( ?object $user = null ) : string
    {
        return (string) ( $user && isset( $user->email ) ? $user->email : request()->ip() );
    }


    /**
     * Formats a number of seconds as a "HH:MM:SS.mmm" timestamp.
     *
     * @param float $seconds Time in seconds
     * @return string Formatted timestamp, e.g. "00:01:23.500"
     */
    public static function formatSeconds( float $seconds ) : string
    {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = floor( $seconds % 60 );
        $millis = ( $seconds - floor( $seconds ) ) * 1000;

        return sprintf( "%02d:%02d:%02d.%03d", $hours, $minutes, $secs, $millis );
    }


    /**
     * Executes a callback within a database transaction using the CMS connection.
     *
     * @template T
     * @param \Closure(): T $callback The callback to execute within the transaction
     * @return T The return value of the callback
     */
    public static function transaction( \Closure $callback ) : mixed
    {
        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( $callback, 3 );
    }


    /**
     * Executes a callback within a cache-locked database transaction for tree safety.
     *
     * @template T
     * @param \Closure(): T $callback The callback to execute within the locked transaction
     * @return T The return value of the callback
     */
    public static function lockedTransaction( \Closure $callback ) : mixed
    {
        return Cache::lock( 'cms_pages_' . Tenancy::value(), 30 )->get( function() use ( $callback ) {
            return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( $callback, 3 );
        } );
    }


    /**
     * Sanitizes SVG/SVGZ content, returns NULL for non-SVG or invalid input.
     *
     * @param mixed $content Raw SVG or gzip-compressed SVGZ content
     * @return string|null Sanitized content (re-compressed if input was SVGZ), or NULL on failure
     */
    public static function cleanSvg( mixed $content ) : string|null
    {
        if( !$content || !is_string( $content ) ) {
            return null;
        }

        $isZip = str_starts_with( $content, "\x1f\x8b" );
        $sanitizer = new \enshrined\svgSanitize\Sanitizer();
        $max = max( 0, (int) ( (float) config( 'cms.upload.filesize', 50 ) * 1024 * 1024 ) );

        if( $isZip )
        {
            $content = @gzdecode( $content, $max + 1 );

            if( $content === false || strlen( $content ) > $max ) {
                throw new Exception( 'Decompressed SVG exceeds the maximum upload size' );
            }
        }

        if( !$content || !( $clean = $sanitizer->sanitize( $content ) ) ) {
            return null;
        }

        $clean = $isZip ? gzencode( $clean ) : $clean;

        if( !$clean ) {
            return null;
        }

        return $clean;
    }


    /**
     * Fetches the contents of an http(s) URL using SSRF-safe options.
     *
     * The host is pinned to its resolved public IP and redirects to private/reserved
     * addresses are blocked, so a stored URL cannot be abused to reach internal services.
     *
     * @param string $url The http(s) URL to fetch
     * @return string The response body
     * @throws Exception If the URL is unsafe or the request fails
     */
    public static function fetch( string $url ) : string
    {
        $response = self::http( $url );

        if( !$response->successful() ) {
            throw new Exception( sprintf( 'URL "%s" not accessible', $url ) );
        }

        return $response->body();
    }


    /**
     * Runs a callback while file ownership changes are serialized per tenant.
     *
     * @template T
     * @param string $tenant Tenant ID owning the file namespace
     * @param \Closure(): T $callback Work that reads or changes file ownership
     * @return T Callback result
     */
    public static function fileLock( string $tenant, \Closure $callback ) : mixed
    {
        $key = 'cms_files_' . hash( 'sha256', $tenant );

        return Cache::lock( $key, 600 )->block( 30, $callback );
    }


    /**
     * Returns a collection of files associated with the given page.
     *
     * @param Page $page The page object containing content and files
     * @return Collection<int, \Aimeos\Cms\Models\File> A collection of File models associated with the page
     */
    public static function files( Page $page ) : Collection
    {
        $seen = [];
        $lang = $page->lang;
        $lang2 = substr( $lang, 0, 2 );

        foreach( (array) $page->content as $item )
        {
            foreach( (array) ( $item->files ?? [] ) as $id )
            {
                if( !isset( $seen[$id] ) && ( $file = $page->files[$id] ?? null ) )
                {
                    $file->description = $file->description->{$lang} ?? $file->description->{$lang2} ?? null;
                    $seen[$id] = $file;
                }
            }
        }

        return new Collection( $seen );
    }


    /**
     * Sanitizes the given HTML text to ensure it is safe for output.
     *
     * @param string|null $text The HTML text to sanitize
     * @return string The sanitized HTML text
     */
    public static function html( ?string $text ) : string
    {
        if( !self::$purifier )
        {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set( 'Attr.AllowedFrameTargets', ['_blank', '_self'] );
            $config->set( 'Cache.SerializerPath', sys_get_temp_dir() );

            self::$purifier = new \HTMLPurifier( $config );
        }

        return self::$purifier->purify( (string) $text );
    }


    /**
     * Sends a GET request while resolving and pinning every redirect target.
     *
     * @param string $url Initial http(s) URL
     * @param array<string, mixed> $options Additional safe HTTP client options
     * @param array<string, string> $headers Request headers
     * @return Response Final response, including unsuccessful non-redirect responses
     */
    public static function http( string $url, array $options = [], array $headers = [] ) : Response
    {
        for( $redirects = 0; ; $redirects++ )
        {
            $response = Http::withHeaders( $headers )
                ->withOptions( self::safeHttp( $url ) + $options )->get( $url );

            if( !in_array( $response->status(), [301, 302, 303, 307, 308], true ) ) {
                return $response;
            }

            $location = trim( $response->header( 'Location' ) );
            $response->toPsrResponse()->getBody()->close();

            if( $location === '' || $redirects >= 2 ) {
                throw new Exception( sprintf( 'Too many or invalid redirects for "%s"', $url ) );
            }

            $url = (string) UriResolver::resolve( new Uri( $url ), new Uri( $location ) );
        }
    }


    /**
     * Returns a file extension that is safe to serve from the storage disk.
     *
     * Neutralizes dangerous uploads/restores (e.g. .php, .html, .phar) by replacing
     * extensions the web server may execute or serve as active content with "bin",
     * so user-supplied files cannot run as code or script.
     *
     * @param string|null $ext File extension (without leading dot)
     * @return string Safe file extension
     */
    public static function extension( ?string $ext ) : string
    {
        $ext = strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $ext ) ) ?: 'bin';

        return match( true ) {
            in_array( $ext, ['htaccess', 'cgi', 'pht', 'phtml', 'phar', 'pl'], true ),
            str_starts_with( $ext, 'php' ),
            str_starts_with( $ext, 'asp' ),
            str_starts_with( $ext, 'jsp' ),
            str_contains( $ext, 'htm' ) => 'bin',
            default => $ext,
        };
    }


    /**
     * Validates a MIME type against configured allowed prefixes.
     *
     * @param string $mime The MIME type to validate
     * @return bool True if the MIME type is allowed, false otherwise
     */
    public static function isValidMimetype( string $mime ) : bool
    {
        $allowed = config( 'cms.upload.mimetypes', [] );

        if( empty( $allowed ) ) {
            return true;
        }

        foreach( $allowed as $prefix )
        {
            if( str_starts_with( $mime, $prefix ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Checks that a local storage path belongs to a tenant namespace.
     *
     * The default tenant may only own direct children of "cms/" so it cannot
     * overlap with named tenant directories.
     */
    public static function isValidPath( mixed $path, ?string $tenant = null ) : bool
    {
        return self::normalizePath( $path, $tenant ) !== null;
    }


    /**
     * Validates an URL, allowing relative paths and absolute http(s) URLs.
     *
     * Rejects protocol-relative URLs (//evil.com), non-http schemes (javascript:, data:),
     * and paths containing control characters.
     *
     * @param string|null $url The URL to validate
     * @param bool $strict Whether to apply strict validation rules
     * @return bool True if the URL is valid, false otherwise
     */
    public static function isValidUrl( ?string $url, bool $strict = true ) : bool
    {
        if( empty( $url ) ) {
            return true;
        }

        if( strlen( $url ) > 2048 ) {
            return false;
        }

        if( ( $parsed = parse_url( $url ) ) === false ) {
            return false;
        }

        // Reject whitespace and control characters in the raw URL
        if( preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
            return false;
        }

        // Reject protocol-relative URLs (//evil.com)
        if( str_starts_with( $url, '//' ) ) {
            return false;
        }

        // Reject paths with directory traversal
        if( !empty( $parsed['path'] ) && str_contains( $parsed['path'], '..' ) ) {
            return false;
        }

        // Relative and absolute paths (no scheme/host) are valid
        if( !$strict && empty( $parsed['scheme'] ) && empty( $parsed['host'] ) ) {
            return true;
        }

        if( empty( $parsed['scheme'] ) || !in_array( $parsed['scheme'], ['http', 'https'] ) ) {
            return false;
        }

        // For http/https URLs, always validate the host
        if( empty( $parsed['host'] ) || !filter_var( $parsed['host'], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            return false;
        }

        // In strict mode, require the host to resolve to an allowed IP address
        if( $strict ) {
            return self::resolve( $parsed['host'] ) !== null;
        }

        return true;
    }


    /**
     * Validates file upload size against configured limit.
     *
     * @param UploadedFile $upload The uploaded file to validate
     * @return bool True if the file size is within the limit, false otherwise
     */
    public static function isValidUpload( UploadedFile $upload ) : bool
    {
        return $upload->getSize() <= config( 'cms.upload.filesize', 50 ) * 1024 * 1024;
    }


    /**
     * Determines the MIME type of a file located at the given path or URL.
     *
     * @param string $path The file path or URL
     * @return string The MIME type of the file
     * @throws Exception If the file cannot be accessed or read
     */
    public static function mimetype( string $path ) : string
    {
        if( str_starts_with( $path, 'http') )
        {
            $response = self::http( $path, ['stream' => true], ['Range' => 'bytes=0-299'] );

            if( !$response->successful() ) {
                throw new Exception( 'URL not accessible' );
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = $body->read( 300 );
            $body->close();
        }
        else
        {
            if( ( $path = self::normalizePath( $path ) ) === null ) {
                throw new Exception( 'Invalid file path' );
            }

            $stream = Storage::disk( config( 'cms.disk', 'public' ) )->readStream( $path );

            if( !$stream ) {
                throw new Exception( 'File not accessible' );
            }

            if( ( $buffer = fread( $stream, 300 ) ) === false ) {
                fclose( $stream );
                throw new Exception( 'File not readable' );
            }

            fclose( $stream );
        }

        $finfo = new \finfo( FILEINFO_MIME_TYPE );

        if( ( $mime = $finfo->buffer( $buffer ) ) === false ) {
            throw new Exception( 'Failed to get mime type' );
        }

        return $mime;
    }


    /**
     * Canonicalizes a local storage path and verifies its tenant namespace.
     *
     * The default tenant may only own direct children of "cms/" so it cannot
     * overlap with named tenant directories.
     *
     * @param mixed $path Local storage path
     * @param string|null $tenant Tenant ID or null for the current tenant
     * @return string|null Canonical path or null if it is invalid
     */
    public static function normalizePath( mixed $path, ?string $tenant = null ) : ?string
    {
        $tenant ??= Tenancy::value();

        if( !is_string( $path ) || $path === '' || str_contains( $path, '..' )
            || str_contains( $path, '\\' ) || preg_match( '/\p{C}/u', $path ) !== 0
            || $tenant === '.' || str_contains( $tenant, '..' )
            || str_contains( $tenant, '/' ) || str_contains( $tenant, '\\' )
            || preg_match( '/\p{C}/u', $tenant ) !== 0 ) {
            return null;
        }

        $path = implode( '/', array_filter(
            explode( '/', $path ),
            static fn( string $part ) : bool => $part !== '' && $part !== '.',
        ) );
        $prefix = $tenant === '' ? 'cms/' : 'cms/' . $tenant . '/';

        if( !str_starts_with( $path, $prefix ) ) {
            return null;
        }

        $relative = substr( $path, strlen( $prefix ) );

        return $relative !== '' && ( $tenant !== '' || !str_contains( $relative, '/' ) )
            ? $path
            : null;
    }


    /**
     * Gets or sets the originating interface for content changes in the current request.
     *
     * Used to tag audit events with their origin: the GraphQL and MCP entry points set 'graphql'
     * resp. 'mcp', everything else (console commands, scheduled jobs) defaults to 'cli'. Stored on
     * the request instance rather than a static, so it neither leaks between requests under Octane
     * nor needs a reset.
     *
     * @param string|null $source Origin to set for this request, or null to only read it
     * @return string The current origin, defaulting to 'cli'
     */
    public static function source( ?string $source = null ) : string
    {
        $request = request();

        if( $source !== null ) {
            $request->attributes->set( 'cms-source', $source );
        }

        $value = $request->attributes->get( 'cms-source', 'cli' );
        return is_string( $value ) ? $value : 'cli';
    }


    /**
     * Resolves a hostname to an allowed IP address.
     *
     * Private and reserved ranges are accepted unless "cms.allow-internal" is
     * disabled. Literal IP hosts are validated directly without a DNS lookup.
     *
     * @param string $host The hostname or IP address to resolve
     * @return string|null The first allowed IP address, or null if none found
     */
    public static function resolve( string $host ) : ?string
    {
        $flags = config( 'cms.allow-internal', true )
            ? 0 : FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        // Literal IP host: validate directly, a DNS lookup would never resolve it
        if( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return filter_var( $host, FILTER_VALIDATE_IP, $flags ) ? $host : null;
        }

        foreach( @dns_get_record( $host, DNS_A + DNS_AAAA ) ?: [] as $r )
        {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;

            if( $ip && filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
                return $ip;
            }
        }

        // dns_get_record( DNS_A | DNS_AAAA ) misses CNAME-only hosts on some
        // resolvers; fall back to the system resolver to avoid rejecting them.
        foreach( @gethostbynamel( $host ) ?: [] as $ip )
        {
            if( filter_var( $ip, FILTER_VALIDATE_IP, $flags ) ) {
                return $ip;
            }
        }

        return null;
    }


    /**
     * Returns Guzzle HTTP options that mitigate SSRF for the given URL.
     *
     * Validates the URL syntactically, resolves the host once and pins the
     * connection to that IP (preventing DNS rebinding). Redirects are disabled
     * here and followed by http(), which repeats validation and pinning for
     * every target. Private/reserved targets are allowed unless
     * "cms.allow-internal" is disabled.
     *
     * @param string $url The http(s) URL that will be fetched
     * @return array<string, mixed> Options to pass to Http::withOptions()
     * @throws Exception If the URL is invalid or the host does not resolve
     */
    public static function safeHttp( string $url ) : array
    {
        // Syntactic validation only; the host is resolved once below and the
        // result reused for both the allow-check and the connection pin.
        if( !self::isValidUrl( $url, false ) ) {
            throw new Exception( sprintf( 'Invalid or unsafe URL "%s"', $url ) );
        }

        $parsed = (array) parse_url( $url );
        $host = (string) ( $parsed['host'] ?? '' );
        $port = $parsed['port'] ?? ( ( $parsed['scheme'] ?? '' ) === 'https' ? 443 : 80 );

        if( !( $ip = self::resolve( $host ) ) ) {
            throw new Exception( sprintf( 'Host "%s" does not resolve to an allowed address', $host ) );
        }

        return [
            'verify' => true,
            'connect_timeout' => 10,
            'allow_redirects' => false,
            'curl' => [CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $ip]],
        ];
    }


    /**
     * Generates a slug from the given title.
     *
     * @param string $title The title to generate a slug from
     * @return string The generated slug
     */
    public static function slugify( string $title ): string
    {
        $title = (string) preg_replace( '/[?&=%#@!$^*()+=\[\]{}|\\"\'<>;:.,_\s]/u', '-', $title );
        $title = (string) preg_replace( '/-+/', '-', $title );
        $title = (string) preg_replace( '/^-|-$/', '', $title );

        return mb_strtolower( trim( $title, '-' ) );
    }


    /**
     * Generates a unique ID for the page content element.
     *
     * This ID is a 6-character string that starts with a letter (A-Z, a-z) and is followed by 5 alphanumeric characters.
     * The first character is chosen from the first 52 characters of the base64 encoding,
     * while the remaining characters can be any of the 64 base64 characters.
     * The ID is based on the current time in milliseconds since a fixed epoch (2025-01-01T00:00:00Z),
     * ensuring that IDs are unique and non-repeating for approximately 70 years.
     *
     * @return string A unique 6-character ID for the page content element
     */
    public static function uid(): string
    {
        $base64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $epoch = strtotime( '2025-01-01T00:00:00Z' ) * 1000;

        $value = ( ( (int) ( ( microtime( true ) * 1000 - $epoch ) / 4096 ) ) << 7 ) | self::$counter;
        self::$counter = ( self::$counter + 1 ) & 0b01111111;

        $id = '';
        for( $i = 0; $i < 6; $i++ )
        {
            // First character: only A-Z/a-z (index % 52), others: full 64-character set
            $index = ($value >> 6 * (5 - $i)) & 63;
            $id .= $base64[$i === 0 ? $index % 52 : $index];
        }

        return $id;
    }
}
