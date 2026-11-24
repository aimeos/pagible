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
    /** Placeholder for the indentation of nested list items, replaced by spaces after the formatting whitespace is removed */
    private const INDENT = "\x1A";

    /** Placeholders for the start and end of blockquotes, all lines in between are prefixed with "> " */
    private const QUOTE_START = "\x02";
    private const QUOTE_END = "\x03";

    /** Placeholder for line breaks, which become hard breaks unless they end a paragraph */
    private const BREAK = "\x1C";

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

        // Blockquotes are marked, so all of their lines can be prefixed after converting their content
        $text = (string) preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n" . self::QUOTE_START . '$1' . self::QUOTE_END . "\n", $text );

        // Line breaks are hard breaks, the whitespace after them is only formatting
        $text = (string) preg_replace( '/<br\s*\/?>\s*/i', self::BREAK . "\n", $text );

        // Lists — convert before inline formatting, whitespace between items isn't content
        $text = static::lists( $text );

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
        ], [
            '**$1**', '**$1**', '*$1*', '*$1*', '~~$1~~', '~~$1~~', '`$1`', "\n---\n", "$1\n\n",
        ], $text );
        $text = strip_tags( $text );
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        // Indentation between list items is formatting whitespace, not list nesting
        $text = (string) preg_replace( '/^[\t ]+(?=(-|\d+\.)\s)/m', '', $text );
        // several line breaks in a row separate paragraphs
        $text = (string) preg_replace( '/' . self::BREAK . '\n(?:[\t ]*' . self::BREAK . '\n)+/', "\n\n", $text );
        $text = (string) preg_replace_callback( '/' . self::QUOTE_START . '(.*?)' . self::QUOTE_END . '/s', function( $m ) {
            $lines = explode( "\n", (string) preg_replace( '/\n{3,}/', "\n\n", trim( $m[1] ) ) );
            return implode( "\n", array_map( fn( $line ) => rtrim( '> ' . $line ), $lines ) );
        }, $text );
        $text = str_replace( [self::QUOTE_START, self::QUOTE_END], '', $text );
        $text = (string) preg_replace( '/\n{3,}/', "\n\n", $text );
        // line breaks at the end of paragraphs and list items aren't shown, the others are hard breaks
        $text = (string) preg_replace( '/[\t ]*' . self::BREAK . '(?=[\t ]*(?:\n[\t >' . self::INDENT . ']*(?:\n|$|(?:-|\d+\.)\s)|$))/', '', $text );
        $text = str_replace( [self::BREAK, self::INDENT], ['\\', ' '], $text );

        return trim( $text );
    }


    /**
     * Converts the HTML lists into Markdown lists, nested lists are indented below their list item.
     *
     * The innermost lists are converted first, so the Markdown of nested lists is part of the item of the outer list.
     */
    protected static function lists( string $text ): string
    {
        $text = (string) preg_replace( '/<\/li>\s*<li\b/is', '</li><li', $text );
        $pattern = '/<(ul|ol)\b([^>]*)>((?:(?!<\/?(?:ul|ol)\b).)*)<\/\1>/is';

        while( preg_match( $pattern, $text ) )
        {
            $text = (string) preg_replace_callback( $pattern, function( $m ) {
                $num = strtolower( $m[1] ) === 'ol' ? (int) ( preg_match( '/\bstart=["\']?(-?\d+)/i', $m[2], $start ) ? $start[1] : 1 ) : null;
                $lines = [];

                preg_match_all( '/<li\b[^>]*>(.*?)<\/li>/is', $m[3], $items );

                foreach( $items[1] as $item )
                {
                    $marker = $num !== null ? $num++ . '. ' : '- ';
                    $parts = array_values( array_filter( array_map( 'trim', explode( "\n", strip_tags( $item, '<strong><b><em><i><a><code>' ) ) ), fn( $line ) => $line !== '' ) );

                    // text and nested lists after the first line belong to the item and are indented by the width of the marker
                    foreach( $parts ?: [''] as $idx => $line ) {
                        $lines[] = $idx === 0 ? $marker . $line : str_repeat( self::INDENT, strlen( $marker ) ) . $line;
                    }
                }

                return "\n" . implode( "\n", $lines ) . "\n\n";
            }, $text );
        }

        return $text;
    }
}
