<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Html as BaseHtml;


/**
 * Converts the HTML of Joomla articles and category descriptions into Pagible content elements.
 *
 * Supports iframes, e.g. of embedded videos or maps, which are removed by the HTML sanitizer otherwise.
 */
class Html extends BaseHtml
{
    /** Warning about a removed iframe, shown once per URL */
    protected const IFRAME_REMOVED = '  Iframe without web URL removed: ":src"';

    /** @var (\Closure(string): void)|null Shows a warning only once, e.g. about removed iframes, ignored if NULL */
    protected ?\Closure $warnOnce = null;


    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     * @param (\Closure(string): void)|null $warn Shows a warning each time, e.g. if processing the HTML failed, ignored if NULL
     * @param (\Closure(string): void)|null $warnOnce Shows a warning only once, e.g. about removed iframes, uses $warn if NULL
     */
    public function __construct( \Closure $image, ?\Closure $warn = null, ?\Closure $warnOnce = null )
    {
        parent::__construct( $image, $warn );

        $this->warnOnce = $warnOnce ?? $warn;
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
     * The decoded source of BaseHtml::decodedSource() is normalized like the images of the articles and
     * categories, see Image::source().
     *
     * @param string $tag HTML image tag with a source like " images/a.jpg"
     * @return array{src: string, alt: string}|null Trimmed source like "images/a.jpg" and alternative text, NULL without source or for inline images
     */
    public static function imageSource( string $tag ): ?array
    {
        if( !( $source = parent::decodedSource( $tag ) ) ) {
            return null;
        }

        $src = Image::source( $source['src'] );
        return $src !== '' ? ['src' => $src, 'alt' => $source['alt']] : null;
    }


    /**
     * Returns the regex patterns of the embedded media which are kept as separate blocks.
     *
     * Iframes become HTML elements, see BaseHtml::iframe().
     *
     * @return string[]
     */
    protected function mediaTags(): array
    {
        return ['iframe'];
    }


    /**
     * Returns the URL of the embedded page for the decoded URL of the iframe tag.
     *
     * @param string $src Decoded URL like "//www.youtube.com/embed/abc"
     * @return string URL with scheme like "https://www.youtube.com/embed/abc"
     */
    protected function iframeSource( string $src ): string
    {
        return Uri::schemed( $src );
    }


    /**
     * Shows a warning about the removed iframe once per URL.
     *
     * @param string $tag Opening iframe tag
     */
    protected function iframeRemoved( string $tag ): void
    {
        if( $this->warnOnce ) {
            ( $this->warnOnce )( strtr( self::IFRAME_REMOVED, [':src' => self::decodedAttribute( $tag, 'src' )] ) );
        }
    }
}
