<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Converts the page breaks of the "Content - Page Break" plugin in the article texts.
 */
class JoomlaPageBreaks
{
    /** Title of the pages without title, ":num" is replaced by the page number */
    protected const UNTITLED_PAGE = 'Page :num';
    /** Tag of the headings replacing the page breaks, Joomla shows the page titles below the article title */
    protected const HEADING_TAG = 'h2';
    /** Regular expression matching the page breaks of the "Content - Page Break" plugin */
    protected const PAGEBREAK_PATTERN = '/<hr\b[^>]*class="system-pagebreak"[^>]*>/i';
    /** Regular expression matching the attributes like Joomla's Utility::parseAttributes(), only double-quoted values with case-sensitive names */
    protected const ATTRIBUTE_PATTERN = '/([\w:-]+)[\s]?=[\s]?"([^"]*)"/';
    /** Regular expression matching the white space including non-breaking spaces at the start and end of a text */
    protected const SPACE_PATTERN = '/^[\s\p{Z}]+|[\s\p{Z}]+$/u';


    /**
     * Replaces the page breaks of the "Content - Page Break" plugin by headings with their titles.
     *
     * Joomla splits the article into pages, tabs or sliders at the page breaks, Pagible shows the whole text.
     * Like the page titles of Joomla's default layout, a non-empty alternative text is used before the title
     * and pages without both are numbered. The attributes are read like Joomla does, see attributes().
     *
     * @param string $html HTML with tags like <hr class="system-pagebreak" title="Part 2" alt="Second part">
     * @param string|null $locale Locale of the titles of pages without title or NULL for the current one
     * @return string HTML with headings like <h2>Second part</h2> instead
     */
    public static function headings( string $html, ?string $locale = null ): string
    {
        $num = 1;

        return (string) preg_replace_callback( self::PAGEBREAK_PATTERN, function( $match ) use ( &$num, $locale ) {
            return self::heading( $match[0], ++$num, $locale );
        }, $html );
    }


    /**
     * Returns the heading replacing the page break.
     *
     * @param string $tag Page break tag like <hr class="system-pagebreak" title="Part 2" alt="Second part">
     * @param int $num Number of the page starting after the page break
     * @param string|null $locale Locale of the title of pages without title or NULL for the current one
     * @return string Heading like <h2>Second part</h2> with the escaped title
     */
    protected static function heading( string $tag, int $num, ?string $locale ): string
    {
        $attrs = self::attributes( $tag );
        $title = self::trim( Html::decode( $attrs['alt'] ?? '' ) );

        // like Joomla's default layout, empty alternative texts are skipped
        if( $title === '' ) {
            $title = self::trim( Html::decode( $attrs['title'] ?? '' ) );
        }

        $text = $title !== '' ? $title : __( self::UNTITLED_PAGE, ['num' => $num], $locale );

        return '<' . self::HEADING_TAG . '>' . e( $text ) . '</' . self::HEADING_TAG . '>';
    }


    /**
     * Returns the attributes of the tag like Joomla's Utility::parseAttributes().
     *
     * Joomla only reads double-quoted values and the names are case-sensitive, so alt='Part' or ALT="Part"
     * are ignored. If an attribute is given several times, the last one is used.
     *
     * @param string $tag Page break tag like <hr class="system-pagebreak" title="Part 2" alt="">
     * @return array<string, string> Attribute names and their raw values with HTML entities
     */
    protected static function attributes( string $tag ): array
    {
        preg_match_all( self::ATTRIBUTE_PATTERN, $tag, $matches, PREG_SET_ORDER );
        return array_column( $matches, 2, 1 );
    }


    /**
     * Removes the white space including non-breaking spaces like decoded "&nbsp;" at the start and end of the text.
     *
     * @param string $text Decoded text
     * @return string Trimmed text, an empty string if it only contains white space
     */
    protected static function trim( string $text ): string
    {
        // invalid UTF-8 can't be matched with Unicode properties, only its ASCII white space is removed then
        return preg_replace( self::SPACE_PATTERN, '', $text ) ?? trim( $text );
    }
}
