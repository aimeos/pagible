<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Markdown helpers shared by the importers.
 */
class Markdown
{
    /**
     * Converts basic HTML to Markdown for Pagible text elements.
     *
     * @param string $html HTML content
     * @return string Markdown text
     */
    public static function fromHtml( string $html ) : string
    {
        $text = str_replace( ["\r\n", "\r"], "\n", $html );

        // Headings within text are kept as separate lines
        $text = (string) preg_replace( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', "\n$1\n", $text );

        // Blockquotes
        $text = (string) preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $text );

        // Lists — convert before inline formatting, whitespace between items isn't content
        $text = (string) preg_replace( '/<\/li>\s*<li\b/is', '</li><li', $text );
        $text = (string) preg_replace_callback( '/<ol[^>]*>(.*?)<\/ol>/is', function( $m ) {
            $i = 0;
            return "\n" . preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function( $li ) use ( &$i ) {
                return ++$i . '. ' . trim( strip_tags( $li[1], '<strong><b><em><i><a><code>' ) ) . "\n";
            }, $m[1] );
        }, $text );

        $text = (string) preg_replace_callback( '/<ul[^>]*>(.*?)<\/ul>/is', function( $m ) {
            return "\n" . (string) preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function( $li ) {
                return '- ' . trim( strip_tags( $li[1], '<strong><b><em><i><a><code>' ) ) . "\n";
            }, $m[1] );
        }, $text );

        // Inline formatting — links need a separate pass due to capture group numbering
        $text = (string) preg_replace( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', '[$2]($1)', $text );
        $text = (string) preg_replace( [
            '/<strong>(.*?)<\/strong>/is',
            '/<b>(.*?)<\/b>/is',
            '/<em>(.*?)<\/em>/is',
            '/<i>(.*?)<\/i>/is',
            '/<del>(.*?)<\/del>/is',
            '/<s>(.*?)<\/s>/is',
            '/<code>(.*?)<\/code>/is',
            '/<hr\s*\/?>/',
            '/<p[^>]*>(.*?)<\/p>/is',
            '/<br\s*\/?>/',
        ], [
            '**$1**', '**$1**', '*$1*', '*$1*', '~~$1~~', '~~$1~~', '`$1`', "\n---\n", "$1\n\n", "\n",
        ], $text );
        $text = strip_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        // Indentation between list items is formatting whitespace, not list nesting
        $text = (string) preg_replace( '/^[\t ]+(?=(-|\d+\.)\s)/m', '', $text );
        $text = (string) preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }
}
