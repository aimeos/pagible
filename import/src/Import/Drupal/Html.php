<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Drupal;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Html as BaseHtml;
use Aimeos\Cms\Import\Markdown;


/**
 * Converts the HTML of Drupal text fields into Pagible content elements.
 *
 * Supports the embedded media and entities, captions and alignments of Drupal's text filters
 * and iframes, videos and audios which are removed by the HTML sanitizer otherwise.
 */
class Html extends BaseHtml
{
    /** @var array<string, bool> HTML tags removed from the converted content */
    protected array $removed = [];


    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     * @param \Closure(string): ?array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>} $embed Returns the content of the <drupal-media> or <drupal-entity> tag
     * @param \Closure(string, string): ?array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} $link Returns the content of the file linked by URL and text or NULL if it's no file of the site
     * @param \Closure(string, string): ?string $media Imports the file of the "video" or "audio" type by its src and returns the file ID
     */
    public function __construct( \Closure $image, protected \Closure $embed, protected \Closure $link, protected \Closure $media )
    {
        parent::__construct( $image );
    }


    /**
     * Returns the caption of the tag added by filter_caption as Markdown.
     */
    public static function caption( string $tag ): string
    {
        return trim( Markdown::fromHtml( self::decodedAttribute( $tag, 'data-caption' ) ) );
    }


    /**
     * Converts HTML into elements, images aligned left or right are placed next to the following text.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function elements( string $html ): array
    {
        $blocks = array_values( array_filter( array_map( 'trim', $this->splitIntoBlocks( str_replace( "\r\n", "\n", $html ) ) ) ) );
        $parts = array_map( fn( $block ) => $this->parseBlock( $block ), $blocks );

        foreach( $parts as $i => $part )
        {
            $element = $part['elements'][0] ?? [];

            if( !( $position = $this->position( $blocks[$i] ) ) || count( $part['elements'] ?? [] ) !== 1 || !in_array( $element['type'] ?? '', ['image', 'image-text'], true ) ) {
                continue;
            }

            // images with captions already are image-text elements
            if( $element['type'] === 'image-text' )
            {
                $element['data']['position'] = $position;
                $parts[$i] = ['elements' => [$element], 'fileIds' => $part['fileIds'] ?? []];
                continue;
            }

            // empty blocks are skipped, e.g. the closing tag of the paragraph containing the image
            for( $j = $i + 1; array_key_exists( $j, $parts ) && $parts[$j] === null; $j++ );

            $next = $parts[$j] ?? null;
            $text = count( $next['elements'] ?? [] ) === 1 && ( $next['elements'][0]['type'] ?? '' ) === 'html'
                ? trim( Markdown::fromHtml( (string) ( $next['elements'][0]['data']['text'] ?? '' ) ) )
                : '';

            if( $text !== '' )
            {
                $parts[$j] = null;
                $parts[$i] = [
                    'elements' => [Elements::create( 'image-text', ['text' => $text, 'file' => $element['data']['file'] ?? null, 'position' => $position] )],
                    'fileIds' => $part['fileIds'] ?? [],
                ];
            }
        }

        return Elements::merge( ...$parts );
    }


    /**
     * Returns the HTML tags removed from the converted content since the last call.
     *
     * @return array<string, bool> Lower case tag names as keys
     */
    public function removed(): array
    {
        $removed = $this->removed;
        $this->removed = [];

        return $removed;
    }


    /**
     * Returns the URL of the remote video of a Drupal Media module file URI.
     *
     * The Media: YouTube, Media: Vimeo and Media: oEmbed modules store remote videos as files
     * with URIs like "youtube://v/ID", "vimeo://v/ID" or "oembed://" and the encoded URL.
     *
     * @param string $uri File URI
     * @return string Video page URL or empty string if the URI isn't a remote video
     */
    public static function streamUrl( string $uri ): string
    {
        return match( true ) {
            (bool) preg_match( '#^youtube://v/([\w-]+)#i', $uri, $m ) => 'https://www.youtube.com/watch?v=' . $m[1],
            (bool) preg_match( '#^vimeo://v/(\d+)#i', $uri, $m ) => 'https://vimeo.com/' . $m[1],
            (bool) preg_match( '#^oembed://(https?(?::|%3A).+)$#i', $uri, $m ) => rawurldecode( $m[1] ),
            default => '',
        };
    }


    /**
     * Returns the embedded media which are kept as separate blocks including the Drupal media elements.
     *
     * @return string[]
     */
    protected function mediaTags(): array
    {
        return ['drupal-entity', 'drupal-media', 'iframe', 'video', 'audio'];
    }


    /**
     * Returns the URL of the embedded page for the decoded URL of the iframe tag.
     *
     * Protocol relative URLs get the HTTPS scheme and Drupal stream wrapper URIs are resolved, see streamUrl().
     *
     * @param string $src Decoded URL like "//www.youtube.com/embed/abc" or "public://maps/a.html"
     * @return string URL with scheme like "https://www.youtube.com/embed/abc"
     */
    protected function iframeSource( string $src ): string
    {
        return str_starts_with( $src, '//' ) ? 'https:' . $src : ( self::streamUrl( $src ) ?: $src );
    }


    /**
     * Remembers the removed iframe for the report of the removed HTML tags, see removed().
     *
     * @param string $tag Opening iframe tag
     */
    protected function iframeRemoved( string $tag ): void
    {
        $this->removed['iframe'] = true;
    }


    /**
     * Parses a block, resolving embedded Drupal media entities to their content.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseBlock( string $block ): ?array
    {
        if( preg_match( '/^<drupal-(?:entity|media)\b[^>]*>/i', $block, $tag ) ) {
            return ( $this->embed )( $tag[0] );
        }

        if( preg_match( '/^<p\b[^>]*>\s*(<a\b[^>]*>)(.*?)<\/a>\s*<\/p>$/is', $block, $link ) && !preg_match( '/<img\b/i', $link[2] )
            && ( $content = ( $this->link )( self::decodedAttribute( $link[1], 'href' ), trim( self::decode( strip_tags( $link[2] ) ) ) ) )
        ) {
            return $content;
        }

        if( preg_match( '/^<(video|audio)\b[^>]*>/i', $block, $tag ) ) {
            return $this->player( strtolower( $tag[1] ), $block );
        }

        return parent::parseBlock( $block );
    }


    /**
     * Parses a standalone image including its caption added by Drupal's filter_caption.
     *
     * If the image can't be imported, the caption is kept as text after the remaining content.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        if( !( $content = parent::parseStandaloneImageBlock( $block ) ) ) {
            return null;
        }

        $tag = preg_match( self::IMAGE_PATTERN, $block, $m ) ? $m[0] : '';

        if( ( $caption = self::caption( $tag ) ) === '' ) {
            return $content;
        }

        if( $fileId = $content['fileIds'][0] ?? null ) {
            return Elements::imageContent( $fileId, $caption ) ?? $content;
        }

        $text = $this->text( self::decodedAttribute( $tag, 'data-caption' ) )['elements'] ?? [];

        return Elements::content( ...$content['elements'], ...$text );
    }


    /**
     * Imports the file of the video or audio tag as video or audio element.
     *
     * @param string $type "video" or "audio"
     * @param string $block HTML of the video or audio tag
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function player( string $type, string $block ): ?array
    {
        preg_match( '/^<' . $type . '\b[^>]*>/i', $block, $tag );
        $src = self::decodedAttribute( $tag[0] ?? '', 'src' );

        if( $src === '' && preg_match( '/<source\b[^>]*>/i', $block, $source ) ) {
            $src = self::decodedAttribute( $source[0], 'src' );
        }

        if( $src === '' || str_starts_with( $src, 'data:' ) )
        {
            $this->removed[$type] = true;
            return null;
        }

        if( !( $fileId = ( $this->media )( $type, $src ) ) ) {
            return null;
        }

        return ['elements' => [Elements::create( $type, ['file' => Elements::file( $fileId )] )], 'fileIds' => [$fileId]];
    }


    /**
     * Returns the position of an image aligned left or right in an image-text element.
     *
     * Drupal stores the alignment in the "data-align" attribute, themes and other editors use CSS classes or styles.
     *
     * @param string $block HTML block containing the image
     * @return string "start" for left, "end" for right aligned images or empty string if not aligned
     */
    protected function position( string $block ): string
    {
        if( !preg_match( '/<(?:img|drupal-media|drupal-entity)\b[^>]*>/i', $block, $tag ) ) {
            return '';
        }

        $align = strtolower( self::decodedAttribute( $tag[0], 'data-align' ) . ' ' . self::decodedAttribute( $tag[0], 'class' ) . ' ' . self::decodedAttribute( $tag[0], 'style' ) );

        return match( preg_match( '/(?:^|[\s-])(left|right)\b|float:\s*(left|right)/', $align, $m ) ? ( $m[1] ?: $m[2] ) : '' ) {
            'left' => 'start',
            'right' => 'end',
            default => '',
        };
    }
}
