<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Creates iframes for embedded pages and videos.
 */
class Embeds
{
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
    public static function iframe( string $url, string $title = '', string $width = '', string $height = '' ): string
    {
        if( !preg_match( '#^https?://#i', $url ) || !Utils::isValidUrl( $url, strict: false ) ) {
            return '';
        }

        $attrs = array_filter( ['src' => $url, 'title' => $title, 'width' => $width, 'height' => $height] );
        $html = implode( ' ', array_map( fn( $name, $value ) => $name . '="' . e( $value ) . '"', array_keys( $attrs ), $attrs ) );

        return '<iframe ' . $html . ' loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>';
    }


    /**
     * Returns the URL of the embeddable player for YouTube and Vimeo videos.
     *
     * @param string $url URL of the video page, e.g. "https://www.youtube.com/watch?v=abc"
     * @return string Player URL or empty string for other URLs
     */
    public static function player( string $url ): string
    {
        if( preg_match( '#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{6,})#i', $url, $m ) ) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }

        if( preg_match( '#^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?#i', $url, $m ) ) {
            return 'https://player.vimeo.com/video/' . $m[1] . ( !empty( $m[2] ) ? '?h=' . $m[2] : '' );
        }

        return '';
    }


    /**
     * Returns the URL usable as iframe source.
     *
     * Video pages can't be embedded, so their player URLs are returned. Other URLs including
     * player URLs are returned unchanged.
     *
     * @param string $url URL of the embedded page or video
     */
    public static function src( string $url ): string
    {
        return !preg_match( '#/(?:embed|video)/#', $url ) && ( $player = self::player( $url ) ) ? $player : $url;
    }
}
