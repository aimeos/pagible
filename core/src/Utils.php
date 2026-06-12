<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
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

        $content = $isZip ? gzdecode( $content ) : $content;

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
        $response = Http::withOptions( self::safeHttp( $url ) )->get( $url );

        if( !$response->successful() ) {
            throw new Exception( sprintf( 'URL "%s" not accessible', $url ) );
        }

        return $response->body();
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
        $allowed = config( 'cms.graphql.mimetypes', [] );

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

        // Strict: DNS lookup and reject private/reserved IPs
        return self::resolve( $parsed['host'] ) !== null;
    }


    /**
     * Validates file upload size against configured limit.
     *
     * @param UploadedFile $upload The uploaded file to validate
     * @return bool True if the file size is within the limit, false otherwise
     */
    public static function isValidUpload( UploadedFile $upload ) : bool
    {
        return $upload->getSize() <= config( 'cms.graphql.filesize', 50 ) * 1024 * 1024;
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
            $response = Http::withHeaders( ['Range' => 'bytes=0-299'] )
                ->withOptions( self::safeHttp( $path ) )
                ->get( $path );

            if( !$response->successful() ) {
                throw new Exception( 'URL not accessible' );
            }

            $buffer = $response->body();
        }
        else
        {
            // Reject traversal sequences and null bytes before reading from the disk so a
            // crafted path (e.g. "cms/1/../2/secret.jpg") can't probe files outside the
            // intended directory and leak their mime type. Stored paths never contain ".."
            // (see File::filename()).
            if( str_contains( $path, '..' ) || str_contains( $path, "\0" ) ) {
                throw new Exception( 'Invalid file path' );
            }

            $stream = Storage::disk( config( 'cms.storage.disk', 'public' ) )->readStream( $path );

            if( !$stream ) {
                throw new Exception( 'File not accessible' );
            }

            if( ( $buffer = fread( $stream, 300 ) ) === false ) {
                fclose($stream);
                throw new Exception( 'File not readable' );

            }

            fclose($stream);
        }

        $finfo = new \finfo( FILEINFO_MIME_TYPE );

        if( ( $mime = $finfo->buffer( $buffer ) ) === false ) {
            throw new Exception( 'Failed to get mime type' );
        }

        return $mime;
    }


    /**
     * Resolves a hostname to a public IP address via DNS.
     *
     * @param string $host The hostname to resolve
     * @return string|null The first public IP address, or null if none found
     */
    public static function resolve( string $host ) : ?string
    {
        foreach( @dns_get_record( $host, DNS_A + DNS_AAAA ) ?: [] as $r )
        {
            $ip = $r['ip'] ?? $r['ipv6'] ?? null;

            if( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                return $ip;
            }
        }

        return null;
    }


    /**
     * Returns Guzzle HTTP options that mitigate SSRF for the given URL.
     *
     * Validates the URL, pins the host to its resolved public IP (preventing
     * DNS rebinding) and blocks redirects to private or reserved addresses.
     *
     * @param string $url The http(s) URL that will be fetched
     * @return array<string, mixed> Options to pass to Http::withOptions()
     * @throws Exception If the URL is invalid or resolves to a non-public address
     */
    public static function safeHttp( string $url ) : array
    {
        if( !self::isValidUrl( $url ) ) {
            throw new Exception( sprintf( 'Invalid or unsafe URL "%s"', $url ) );
        }

        $parsed = (array) parse_url( $url );
        $host = (string) ( $parsed['host'] ?? '' );
        $port = $parsed['port'] ?? ( ( $parsed['scheme'] ?? '' ) === 'https' ? 443 : 80 );

        if( !( $ip = self::resolve( $host ) ) ) {
            throw new Exception( sprintf( 'Host "%s" does not resolve to a public address', $host ) );
        }

        return [
            'verify' => true,
            'connect_timeout' => 10,
            'allow_redirects' => [
                'max' => 2,
                'strict' => true,
                'on_redirect' => function( $request, $response, \Psr\Http\Message\UriInterface $uri ) {
                    if( !( $host = $uri->getHost() ) || !self::resolve( $host ) ) {
                        throw new Exception( sprintf( 'Redirect to "%s" blocked', $host ) );
                    }
                },
            ],
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