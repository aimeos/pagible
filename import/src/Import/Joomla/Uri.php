<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Parses the URLs of the Joomla content like the Uri class of Joomla does.
 *
 * Returns the decoded path, the query parameters and the IDs of their parameters.
 */
class Uri
{
    /**
     * Returns the ID of a link query parameter like "id" or "Itemid".
     *
     * Like Joomla, IDs can contain the alias, e.g. "5:about".
     *
     * @return int ID or 0 if the value isn't one
     */
    public static function id( mixed $value ): int
    {
        return is_string( $value ) || is_int( $value ) ? (int) $value : 0;
    }


    /**
     * Returns the URL with the HTTPS scheme if it has none, e.g. "//example.com/page".
     *
     * URLs without scheme use the one of the page, which is HTTPS nowadays.
     */
    public static function schemed( string $url ): string
    {
        return str_starts_with( $url, '//' ) ? 'https:' . $url : $url;
    }


    /**
     * Splits the URL into the part before the query string or fragment and the rest.
     *
     * Unlike parse_url(), the parts are kept as they are, so they can be joined again after changing the path.
     *
     * @param string $url URL or URL path, e.g. "../page.html?x=1#top"
     * @return array{0: string, 1: string} Part before the first "?" or "#" and the query string and fragment with
     *  "?" or "#" or an empty string if there's none, e.g. ["../page.html", "?x=1#top"]
     */
    public static function split( string $url ): array
    {
        $length = strcspn( $url, '?#' );
        return [substr( $url, 0, $length ), substr( $url, $length )];
    }


    /**
     * Returns the decoded URL path with leading slash.
     *
     * @param string|null $path Encoded URL path like "old%20page" or NULL if the URL has none
     * @return string Decoded URL path like "/old page"
     */
    public static function decodedPath( ?string $path ): string
    {
        return rawurldecode( '/' . ltrim( $path ?? '', '/' ) );
    }


    /**
     * Returns the parameters of the query string of a Joomla URL.
     *
     * @param string $query Query string like "option=com_content&view=article&id=5"
     * @return array<array-key, mixed> Query parameters
     */
    public static function query( string $query ): array
    {
        parse_str( $query, $params );
        return $params;
    }
}
