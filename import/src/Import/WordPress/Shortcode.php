<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;


/**
 * Parses the shortcodes of WordPress core in post contents like get_shortcode_regex() and shortcode_parse_atts().
 */
class Shortcode
{
    /** Names of the WordPress core shortcodes and the code shortcodes of WordPress.com which are converted */
    public const TAGS = ['caption', 'wp_caption', 'gallery', 'playlist', 'embed', 'video', 'audio', 'code', 'sourcecode'];
    /** Extensions of the files played by the video and audio shortcodes, see wp_get_video_extensions() and wp_get_audio_extensions() */
    public const EXTENSIONS = ['video' => ['mp4', 'm4v', 'webm', 'ogv', 'flv'], 'audio' => ['mp3', 'ogg', 'flac', 'm4a', 'wav']];


    /**
     * Returns the regular expression matching the shortcodes, the same as get_shortcode_regex() of WordPress.
     *
     * Groups: 1 = "[" of escaped shortcodes like "[[gallery]]", 2 = name, 3 = attributes, 4 = "/" of self-closing
     * shortcodes, 5 = content of enclosing shortcodes, 6 = "]" of escaped shortcodes.
     */
    public static function pattern(): string
    {
        $tags = implode( '|', array_map( fn( $tag ) => preg_quote( $tag, '/' ), static::TAGS ) );

        return '/\[(\[?)(' . $tags . ')(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)'
            . '(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/';
    }


    /**
     * Returns the named attributes of a shortcode like shortcode_parse_atts() of WordPress.
     *
     * @param string $text Attributes of the shortcode like ' ids="1,2" columns=3'
     * @return array<string, string> Attribute values by lower case name, values without name are skipped
     */
    public static function attributes( string $text ): array
    {
        $attrs = [];
        $text = (string) preg_replace( '/[\x{00a0}\x{200b}]+/u', ' ', $text );
        $pattern = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"[^"]*"(?:\s|$)|\'[^\']*\'(?:\s|$)|\S+(?:\s|$)/';

        preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER );

        foreach( $matches as $m )
        {
            foreach( [1, 3, 5] as $idx )
            {
                // trailing groups which didn't match aren't part of the match
                if( ( $name = $m[$idx] ?? '' ) !== '' ) {
                    $attrs[strtolower( $name )] = stripcslashes( $m[$idx + 1] ?? '' );
                    break;
                }
            }
        }

        return $attrs;
    }


    /**
     * Returns the IDs of the image attachments of the gallery shortcodes in the post content.
     *
     * @return array<int, int> Attachment IDs from the "ids" or "include" attributes in content order
     */
    public static function galleryIds( string $html ): array
    {
        $ids = [];

        if( !str_contains( $html, '[gallery' ) || !preg_match_all( static::pattern(), $html, $matches, PREG_SET_ORDER ) ) {
            return [];
        }

        foreach( $matches as $m )
        {
            if( $m[2] === 'gallery' && !( $m[1] === '[' && $m[6] === ']' ) ) {
                array_push( $ids, ...static::ids( static::attributes( $m[3] ) ) );
            }
        }

        return $ids;
    }


    /**
     * Returns the attachment IDs of a gallery shortcode.
     *
     * @param array<string, string> $attrs Attributes of the gallery shortcode
     * @return array<int, int> Attachment IDs from the "ids" attribute or the "include" attribute of old galleries
     */
    public static function ids( array $attrs ): array
    {
        $ids = array_map( 'trim', explode( ',', $attrs['ids'] ?? $attrs['include'] ?? '' ) );
        return array_values( array_map( 'intval', array_filter( $ids, fn( $id ) => ctype_digit( $id ) && $id !== '0' ) ) );
    }


    /**
     * Returns the URL of the file of a video or audio shortcode like wp_video_shortcode() and wp_audio_shortcode().
     *
     * The attributes named like the file extensions are only used if their URL ends with that extension.
     *
     * @param string $tag "video" or "audio"
     * @param array<string, string> $attrs Attributes of the shortcode, the URL is in "src" or the attribute of the file extension
     * @return string URL of the file or empty if the shortcode shows the first file attached to the post
     */
    public static function source( string $tag, array $attrs ): string
    {
        if( ( $url = trim( $attrs['src'] ?? '' ) ) !== '' ) {
            return $url;
        }

        foreach( static::EXTENSIONS[$tag] ?? [] as $ext )
        {
            if( ( $url = trim( $attrs[$ext] ?? '' ) ) !== '' && static::extension( $url ) === $ext ) {
                return $url;
            }
        }

        return '';
    }


    /**
     * Tests if the video or audio shortcode plays the file, otherwise WordPress shows a link to the URL.
     *
     * Like wp_video_shortcode() and wp_audio_shortcode(), only files with the extensions of wp_get_video_extensions()
     * and wp_get_audio_extensions() and YouTube and Vimeo videos are played. Files attached to the post, which were
     * added by Reader::playlists() with the "attached" attribute, are played whatever their type is.
     *
     * @param string $tag "video" or "audio"
     * @param array<string, string> $attrs Attributes of the shortcode
     * @param string $url URL of the file returned by source()
     */
    public static function playable( string $tag, array $attrs, string $url ): bool
    {
        if( isset( $attrs['attached'] ) ) {
            return true;
        }

        if( $tag === 'video' && preg_match( '#^https?://(?:(?:www\.)?(?:youtube\.com/watch|youtu\.be/)|(?:.+\.)?vimeo\.com/)#', $url ) ) {
            return true;
        }

        return in_array( static::extension( $url ), static::EXTENSIONS[$tag] ?? [], true );
    }


    /**
     * Removes the shortcodes including their content like strip_shortcodes() of WordPress, escaped ones become text.
     */
    public static function strip( string $html ): string
    {
        if( !str_contains( $html, '[' ) ) {
            return $html;
        }

        return (string) preg_replace_callback( static::pattern(), fn( $m ) => $m[1] === '[' && $m[6] === ']' ? substr( $m[0], 1, -1 ) : '', $html );
    }


    /**
     * Returns the lower case extension at the end of the URL like wp_check_filetype(), URLs with query strings have none.
     */
    protected static function extension( string $url ): string
    {
        return preg_match( '/\.([a-z0-9]+)$/i', $url, $m ) ? strtolower( $m[1] ) : '';
    }
}
