<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Converts HTML into Pagible content elements.
 */
class Html
{
    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     */
    public function __construct( protected \Closure $image )
    {
    }


    /**
     * Returns the source URL and alternative text of the HTML image tag.
     *
     * @param string $tag HTML image tag, e.g. "<img src="photo.jpg" alt="Photo">"
     * @return array{src: string, alt: string}|null Source and alternative text or NULL if the tag has no source
     */
    public static function source( string $tag ): ?array
    {
        if( !preg_match( '/src=["\']([^"\']+)["\']/i', $tag, $src ) ) {
            return null;
        }

        return ['src' => $src[1], 'alt' => preg_match( '/alt=["\']([^"\']*)["\']/', $tag, $alt ) ? $alt[1] : ''];
    }


    /**
     * Converts HTML into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function elements( string $html ): array
    {
        $blocks = array_filter( array_map( 'trim', $this->splitIntoBlocks( str_replace( "\r\n", "\n", $html ) ) ) );

        return Elements::merge( ...array_map( fn( $block ) => $this->parseBlock( $block ), $blocks ) );
    }


    /**
     * Converts a plain HTML block into an HTML element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    public function text( string $block ): ?array
    {
        $html = trim( Utils::html( trim( $block ) ) );

        // tags left over from splitting paragraphs around images, e.g. "<p>" and "</p>" of "<p><img></p>"
        if( preg_match( '/^(?:<p>\s*<\/p>|\s)*$/i', $html ) ) {
            return null;
        }

        return ['elements' => [Elements::create( 'html', ['text' => $html] )]];
    }


    /**
     * Returns the regex patterns of the elements which are kept as separate blocks.
     *
     * @return string[]
     */
    protected function blockTags(): array
    {
        return ['h[1-6]', 'pre', 'figure'];
    }


    /**
     * Imports the image of the HTML tag.
     *
     * @return string|null File ID or NULL if the image can't be imported
     */
    protected function image( string $tag ): ?string
    {
        return ( $this->image )( $tag );
    }


    /**
     * Dispatches a single HTML block to the appropriate parser.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseBlock( string $block ): ?array
    {
        if( $result = $this->parseHeadingBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseCodeBlock( $block ) ) {
            return $result;
        }

        if( $result = $this->parseStandaloneImageBlock( $block ) ) {
            return $result;
        }

        if( preg_match( '/<img[^>]+>/i', $block ) ) {
            return $this->parseImageTextBlock( $block );
        }

        return $this->text( $block );
    }


    /**
     * Parses a preformatted block into a code element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function parseCodeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<pre[^>]*>(.*?)<\/pre>$/is', $block, $matches ) ) {
            return null;
        }

        $language = '';
        if( preg_match( '/class=["\'][^"\']*language-(\w+)/i', $block, $langMatch ) ) {
            $language = strtolower( $langMatch[1] );
        }

        $code = (string) preg_replace( '/<\/?code[^>]*>/i', '', $matches[1] );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

        return ['elements' => [Elements::create( 'code', [
            'language' => $language,
            'text' => trim( $code ),
        ] )]];
    }


    /**
     * Parses an HTML heading into a heading element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function parseHeadingBlock( string $block ): ?array
    {
        if( !preg_match( '/^<h([1-6])[^>]*>(.*?)<\/h[1-6]>$/is', $block, $matches ) ) {
            return null;
        }

        return ['elements' => [Elements::create( 'heading', [
            'level' => (int) $matches[1],
            'title' => strip_tags( $matches[2] ),
        ] )]];
    }


    /**
     * Parses a text block containing an inline image.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function parseImageTextBlock( string $html ): array
    {
        if( !preg_match( '/<(?:a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $html, $matches ) ) {
            $result = $this->text( $html );

            return ['elements' => $result['elements'] ?? [], 'fileIds' => []];
        }

        $fileId = $this->image( $matches[0] );
        $text = trim( str_replace( $matches[0], '', $html ) );

        if( $content = Elements::imageContent( $fileId, $text ) ) {
            return $content;
        }

        return ['elements' => $text !== '' ? [Elements::create( 'html', ['text' => Utils::html( $text )] )] : [], 'fileIds' => []];
    }


    /**
     * Parses a standalone image into an image element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        $linked = preg_match( '/^<a[^>]*>\s*<img[^>]+>\s*<\/a>$/is', $block );
        $bare = preg_match( '/^<img\b[^>]*>$/is', $block );
        $figure = preg_match( '/^<figure[^>]*>\s*<img[^>]+>.*?<\/figure>$/is', $block );

        if( !$linked && !$bare && !$figure ) {
            return null;
        }

        return Elements::imageContent( $this->image( $block ) );
    }


    /**
     * Splits HTML content into logical blocks.
     *
     * @return string[]
     */
    protected function splitIntoBlocks( string $html ): array
    {
        $tags = $this->blockTags();
        $pattern = '/(' . implode( '|', array_map( fn( $tag ) => "<{$tag}[^>]*>.*?<\\/{$tag}>", $tags ) ) . ')/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];
        $blocks = [];

        foreach( $parts as $part )
        {
            if( ( $part = trim( $part ) ) === '' ) {
                continue;
            }

            if( preg_match( '/^<(' . implode( '|', $tags ) . ')/i', $part ) ) {
                $blocks[] = $part;
                continue;
            }

            $subparts = preg_split( '/(\n\s*\n|(?:<a[^>]*>\s*<img[^>]+>\s*<\/a>)|(?:<img[^>]+\/?>))/is', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

            foreach( $subparts as $subpart ) {
                if( ( $subpart = trim( $subpart ) ) !== '' ) {
                    $blocks[] = $subpart;
                }
            }
        }

        return $blocks;
    }
}
