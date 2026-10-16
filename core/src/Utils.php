<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;


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
     * Registers a per-minute rate limiter keyed by the user ID or the client IP.
     *
     * @param string $name Rate limiter name used by the "throttle" middleware
     * @param int $perMinute Maximum number of requests per minute
     * @param bool $user TRUE to key by the authenticated user ID if available, FALSE to key by IP only
     */
    public static function limit( string $name, int $perMinute, bool $user = true ) : void
    {
        RateLimiter::for( $name, fn( $request ) => Limit::perMinute( $perMinute )->by(
            $user ? ( $request->user()?->getAuthIdentifier() ?: $request->ip() ) : $request->ip()
        ) );
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

        // Reject traversal segments at every encoding layer so intermediaries can't expose one
        $path = (string) ( $parsed['path'] ?? '' );

        do
        {
            if( preg_match( '~(?:^|[/\\\\])\.\.(?:[/\\\\]|$)~', $path ) ) {
                return false;
            }

            $previous = $path;
            $path = rawurldecode( $path );
        }
        while( $path !== $previous );

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
            return SafeHttp::resolve( $parsed['host'] ) !== null;
        }

        return true;
    }


    /**
     * Returns the request host if multiple domains are enabled.
     *
     * @return string Request host or empty string if "cms.multidomain" is disabled
     */
    public static function domain() : string
    {
        return config( 'cms.multidomain' ) ? request()->getHost() : '';
    }


    /**
     * Returns the frontend URL of a page if the page route of the theme package is available.
     *
     * @param string $path Page URL path
     * @param string|null $domain Page domain, the current host is used if empty
     * @return string|null Absolute page URL or NULL if pages aren't rendered by the CMS
     */
    public static function pageUrl( string $path, ?string $domain = null ) : ?string
    {
        if( !Route::has( 'cms.page' ) ) {
            return null;
        }

        $params = config( 'cms.multidomain' ) ? ['domain' => $domain ?: request()->getHost()] : [];
        return route( 'cms.page', $params + ['path' => $path] );
    }


    /**
     * Tests if the value is a valid language code like "de" or "zh-Hant".
     *
     * @param string $code Language code
     * @return bool TRUE if the code is valid
     */
    public static function isValidLang( string $code ) : bool
    {
        return strlen( $code ) <= 10 && preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $code ) === 1;
    }


    /**
     * Tests if the value is a valid language code from "cms.locales" (if configured).
     *
     * @param string $code Language code
     * @return bool TRUE if the code is valid and configured
     */
    public static function isLocale( string $code ) : bool
    {
        $locales = self::locales();
        return self::isValidLang( $code ) && ( !$locales || in_array( $code, $locales, true ) );
    }


    /**
     * Returns the configured language codes.
     *
     * @return array<int, string> Language codes from "cms.locales"
     */
    public static function locales() : array
    {
        return array_map( 'strval', (array) config( 'cms.locales', [] ) );
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
