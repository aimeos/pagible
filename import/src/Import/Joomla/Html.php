<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Embeds;
use Aimeos\Cms\Import\Html as BaseHtml;


/**
 * Converts the HTML of Joomla articles and category descriptions into Pagible content elements.
 *
 * Supports iframes, e.g. of embedded videos or maps, which are removed by the HTML sanitizer otherwise.
 */
class Html extends BaseHtml
{
    /** Regular expression matching an iframe tag at the start of a block */
    protected const IFRAME_PATTERN = '/^<iframe\b[^>]*>/i';
    /** Warning about a removed iframe, shown once per URL */
    protected const IFRAME_REMOVED = 'Iframe without web URL removed: ":src"';


    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     * @param (\Closure(string): void)|null $warn Shows a warning only once, e.g. about removed iframes, ignored if NULL
     */
    public function __construct( \Closure $image, protected ?\Closure $warn = null )
    {
        parent::__construct( $image );
    }


    /**
     * Returns the sources of the images in the HTML like the ones imported, so the prefetched files are found again.
     *
     * The sources are the ones of imageSource(): decoded, trimmed and without the suffix of the Joomla 4+ media field.
     * The lazy load attributes like "data-src" are preferred over "src" like by BaseHtml::source().
     *
     * @return list<string> Decoded sources in the order of the images, empty ones and inline images are skipped
     */
    public static function sources( string $html ): array
    {
        preg_match_all( self::IMAGE_PATTERN, $html, $matches );
        $sources = array_map( fn( $tag ) => self::imageSource( $tag )['src'] ?? '', $matches[0] );
        return array_values( array_filter( $sources, fn( $src ) => $src !== '' ) );
    }


    /**
     * Returns the source and alternative text of the HTML image tag which is imported.
     *
     * The decoded source of BaseHtml::decodedSource() is trimmed and the suffix of the Joomla 4+ media field is
     * removed like for the images of the articles and categories, see Texts::imageSource().
     *
     * @param string $tag HTML image tag with a source like " images/a.jpg"
     * @return array{src: string, alt: string}|null Trimmed source like "images/a.jpg" and alternative text, NULL without source or for inline images
     */
    public static function imageSource( string $tag ): ?array
    {
        if( !( $source = parent::decodedSource( $tag ) ) ) {
            return null;
        }

        $src = trim( Image::path( $source['src'] ) );
        return $src !== '' && !self::inline( $src ) ? ['src' => $src, 'alt' => $source['alt']] : null;
    }


    /**
     * Tests if the image source is a data URI containing the image instead of an URL.
     *
     * @param string $src Decoded source of the image
     */
    public static function inline( string $src ): bool
    {
        return strncasecmp( ltrim( $src ), 'data:', 5 ) === 0;
    }


    /**
     * Returns the regex patterns of the embedded media which are kept as separate blocks.
     *
     * @return string[]
     */
    protected function mediaTags(): array
    {
        return ['iframe'];
    }


    /**
     * Parses a single HTML block, iframes become HTML elements.
     *
     * The iframe is created again from its URL, size and title because the HTML element isn't
     * sanitized. Iframes with other URLs than web URLs are removed with a warning. Iframes in figures
     * are split from their images and captions before, see BaseHtml::splitFigure().
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseBlock( string $block ): ?array
    {
        if( !preg_match( self::IFRAME_PATTERN, $block, $tag ) ) {
            return parent::parseBlock( $block );
        }

        if( !( $content = Embeds::iframeTag( $tag[0], Uri::schemed( ... ) ) ) && $this->warn ) {
            ( $this->warn )( strtr( self::IFRAME_REMOVED, [':src' => self::decodedAttribute( $tag[0], 'src' )] ) );
        }

        return $content;
    }
}
