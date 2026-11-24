<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;

use Aimeos\Cms\Utils;


/**
 * Creates the article element of WordPress posts imported as blog articles.
 */
class Article
{
    /** Blocks used for excerpts like excerpt_remove_blocks() of WordPress including the wrapper and list item blocks */
    protected const EXCERPT_BLOCKS = 'column|columns|freeform|group|heading|html|list-item|list|media-text|paragraph|preformatted|pullquote|quote|table|verse';

    /**
     * Returns the article element with the introduction and the cover image of the post.
     *
     * @param string $intro Introduction text of the post
     * @param string|null $coverFileId ID of the featured image shown in the article element
     * @param string|null $previewFileId ID of the image used as preview if there's no featured image
     * @return array<string, mixed> Article element
     */
    public static function element( string $intro, ?string $coverFileId, ?string $previewFileId = null ): array
    {
        $data = ['text' => $intro];
        $files = $previewFileId ? [$previewFileId] : [];

        if( $coverFileId ) {
            $data['file'] = ['id' => $coverFileId, 'type' => 'file'];
            $files = [$coverFileId];
        }

        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => $files, 'data' => $data];
    }


    /**
     * Returns the plain text of the manual excerpt of the post, which may contain HTML tags.
     */
    public static function excerpt( string $html ): string
    {
        return static::plain( $html );
    }


    /**
     * Returns the plain text of the post content before the "more" tag as introduction, shortened to 300 characters.
     *
     * The "more" tag can contain the text of the link like "<!--more Read on-->", see get_extended() of WordPress.
     * Like wp_trim_excerpt(), blocks which aren't text like images, embeds or code and footnote references are removed
     * and longer texts are shortened after the last complete word, see wp_trim_words().
     */
    public static function intro( string $html ): string
    {
        $html = preg_split( '/<!--more(.*?)?-->/', $html, 2 )[0] ?? $html;
        $html = (string) preg_replace( '/<!--\s+wp:(?!(?:core\/)?(?:' . static::EXCERPT_BLOCKS . ')[\s\/])([a-z][a-z0-9_\-]*(?:\/[a-z][a-z0-9_\-]*)?)\s'
            . '(?:[^>]*?\/-->|.*?<!--\s+\/wp:\1\s+-->)/s', '', $html );
        $html = (string) preg_replace( '/<sup\b[^>]*\bdata-fn=[^>]*>.*?<\/sup>/is', '', $html );
        $text = static::plain( $html );

        if( mb_strlen( $text ) <= 300 ) {
            return $text;
        }

        $cut = mb_substr( $text, 0, 299 );

        // a word cut in the middle is removed, a single long word is cut
        if( !preg_match( '/^\s/u', mb_substr( $text, 299, 1 ) ) && preg_match( '/^(.*\S)\s+\S*$/su', $cut, $m ) ) {
            $cut = $m[1];
        }

        return rtrim( $cut ) . '…';
    }


    /**
     * Returns the plain text of the HTML with single spaces.
     */
    protected static function plain( string $html ): string
    {
        // shortcodes are removed like by the excerpts of WordPress, otherwise the captions of images would be in the text
        // words of adjacent blocks without white space in between must remain separated
        $html = (string) preg_replace( '/<\/(?:p|li|h[1-6]|div|td|th|blockquote|figcaption|pre)>|<br\s*\/?>/i', '$0 ', $html );
        $text = strip_tags( Shortcode::strip( $html ) );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        $text = (string) preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }
}
