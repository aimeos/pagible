<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Creates iframes for embedded pages and videos.
 *
 * @phpstan-import-type ContentElements from Elements
 */
class Embeds
{
    /**
     * Returns the content with an HTML element containing the iframe created again from the attributes of the iframe tag.
     *
     * @param string $tag Opening iframe tag whose attributes contain HTML entities
     * @param \Closure(string): string $src Returns the URL of the embedded page for the decoded URL of the tag
     * @return ContentElements|null Content or NULL if the URL isn't a web URL
     */
    public static function iframeTag( string $tag, \Closure $src ): ?array
    {
        $attr = fn( string $name ) => Html::decodedAttribute( $tag, $name );
        return self::iframeContent( $src( $attr( 'src' ) ), $attr( 'title' ), $attr( 'width' ), $attr( 'height' ) );
    }


    /**
     * Returns the content with an HTML element containing the iframe created again from its attributes.
     *
     * The iframe is created again because the HTML element isn't sanitized. Video page URLs are
     * replaced by their player URLs and sizes other than pixels or percent are skipped.
     *
     * @param string $src Decoded URL of the embedded page or video
     * @param string $title Decoded title of the iframe for screen readers
     * @param string $width Decoded width of the iframe
     * @param string $height Decoded height of the iframe
     * @return ContentElements|null Content or NULL if the URL isn't a web URL
     */
    public static function iframeContent( string $src, string $title = '', string $width = '', string $height = '' ): ?array
    {
        $size = fn( string $value ) => preg_match( '/^\d+%?$/', $value ) ? $value : '';

        if( !( $html = self::iframe( self::src( $src ), $title, $size( $width ), $size( $height ) ) ) ) {
            return null;
        }

        return Elements::content( Elements::create( 'html', ['text' => $html] ) );
    }


    /**
     * Returns the HTML code of the iframe or an empty string if the URL isn't a web URL.
     *
     * Only web URLs are allowed because the iframe isn't sanitized like the other HTML elements.
     *
     * @param string $url URL of the embedded page
     * @param string $title Title of the iframe for screen readers
     * @param string $width Width in pixels or percent, empty for none
     * @param string $height Height in pixels or percent, empty for none
     */
    protected static function iframe( string $url, string $title = '', string $width = '', string $height = '' ): string
    {
        if( !preg_match( '#^https?://#i', $url ) || !Utils::isValidUrl( $url, strict: false ) ) {
            return '';
        }

        $attrs = array_filter( ['src' => $url, 'title' => $title, 'width' => $width, 'height' => $height] );
        $html = implode( ' ', array_map( fn( $name, $value ) => $name . '="' . e( $value ) . '"', array_keys( $attrs ), $attrs ) );

        return '<iframe ' . $html . ' loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';
    }


    /**
     * Returns the URL of the embeddable player for YouTube, Vimeo and Cloudflare Stream videos.
     *
     * @param string $url URL of the video page, e.g. "https://www.youtube.com/watch?v=abc"
     * @return string Player URL or empty string for other URLs
     */
    public static function player( string $url ): string
    {
        if( preg_match( '#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|v/|shorts/|live/)|youtu\.be/)([\w-]{6,})#i', $url, $m ) ) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . ( ( $start = self::start( $url ) ) ? '?start=' . $start : '' );
        }

        // Vimeo doesn't track the viewers with "dnt=1" like Statamic embeds the videos
        if( preg_match( '#^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?#i', $url, $m ) ) {
            return 'https://player.vimeo.com/video/' . $m[1] . ( !empty( $m[2] ) ? '?h=' . $m[2] . '&' : '?' ) . 'dnt=1'
                . ( ( $start = self::start( $url ) ) ? '#t=' . $start . 's' : '' );
        }

        if( preg_match( '#^https?://(customer-[a-z0-9]+\.cloudflarestream\.com)/([0-9a-f]{32})(?:[/?\#]|$)#i', $url, $m ) ) {
            return 'https://' . strtolower( $m[1] ) . '/' . $m[2] . '/iframe';
        }

        if( preg_match( '#^https?://(?:iframe\.|watch\.)?videodelivery\.net/([0-9a-f]{32})(?:[/?\#]|$)#i', $url, $m ) ) {
            return 'https://iframe.videodelivery.net/' . $m[1];
        }

        return '';
    }


    /**
     * Returns the start time of the video from the "t" or "start" parameter of the URL or its fragment.
     *
     * @param string $url URL of the video page like "https://youtu.be/abc?t=1m30s"
     * @return int Start time in seconds or 0 if the video starts at the beginning
     */
    protected static function start( string $url ): int
    {
        $params = [];
        parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $params );
        $fragment = [];
        parse_str( (string) parse_url( $url, PHP_URL_FRAGMENT ), $fragment );

        $value = $params['t'] ?? $params['start'] ?? $fragment['t'] ?? $fragment['start'] ?? '';

        // times are given in seconds like "90" or "90s" or like "1h2m30s"
        if( !is_string( $value ) || !preg_match( '/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/i', trim( $value ), $m ) ) {
            return 0;
        }

        return (int) ( $m[1] ?? 0 ) * 3600 + (int) ( $m[2] ?? 0 ) * 60 + (int) ( $m[3] ?? 0 );
    }


    /**
     * Returns the URL usable as iframe source.
     *
     * Video pages can't be embedded, so their player URLs are returned. Other URLs including
     * player URLs are returned unchanged.
     *
     * @param string $url URL of the embedded page or video
     */
    protected static function src( string $url ): string
    {
        return !preg_match( '#/(?:embed|video)/#', $url ) && ( $player = self::player( $url ) ) ? $player : $url;
    }
}
