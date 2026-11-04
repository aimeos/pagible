<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Converts the content of WordPress posts into Pagible content elements.
 *
 * Gutenberg block comments are used as type hints, classic content is split at headings,
 * preformatted texts, notices, images and empty lines. Texts which can be converted to
 * Markdown become text elements, all others are kept as HTML.
 */
class WordPress extends Html
{
    /** JSON attributes of a block comment, which may contain nested objects but not the end of the comment */
    private const ATTRS = '\{(?:(?!-->).)*?\}';

    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     * @param \Closure(string): ?string $file Imports the audio or video file of the URL and returns the file ID
     */
    public function __construct( \Closure $image, protected \Closure $file )
    {
        parent::__construct( $image );
    }


    /**
     * Converts the WordPress post content into Pagible content elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function elements( string $html ): array
    {
        $html = (string) preg_replace( '/<!--more-->/', '', $html );
        $html = str_replace( "\r\n", "\n", $html );

        $elements = [];
        $fileIds = [];

        // Detect Gutenberg content and use block comments as type hints
        $results = preg_match( '/<!--\s*wp:/', $html ) ? $this->gutenbergBlocks( $html ) : $this->htmlBlocks( $html );

        foreach( array_filter( $results ) as $result )
        {
            array_push( $elements, ...$result['elements'] );
            array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
        }

        return ['elements' => $this->mergeConsecutiveText( $elements ), 'fileIds' => $fileIds];
    }


    /**
     * Converts the Gutenberg blocks into content.
     *
     * @return array<int, array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null>
     */
    protected function gutenbergBlocks( string $html ): array
    {
        $results = [];
        $entries = $this->splitGutenbergBlocks( $html );
        $count = count( $entries );

        for( $i = 0; $i < $count; $i++ )
        {
            $entry = $entries[$i];
            $block = trim( $this->stripWpComments( $entry['html'] ) );
            if( empty( $block ) ) {
                continue;
            }

            // Merge aligned image with following paragraph blocks into image-text
            $align = $entry['attrs']['align'] ?? '';
            if( $entry['type'] === 'image' && ( $align === 'left' || $align === 'right' ) )
            {
                $fileId = $this->image( $block );
                $textParts = [];

                // Collect following paragraph/list/quote blocks as text
                while( $i + 1 < $count && in_array( $entries[$i + 1]['type'], ['paragraph', 'list', 'quote', 'pullquote'] ) )
                {
                    $i++;
                    $nextBlock = trim( $this->stripWpComments( $entries[$i]['html'] ) );
                    if( !empty( $nextBlock ) ) {
                        $textParts[] = $nextBlock;
                    }
                }

                $results[] = $this->imageText( $fileId, implode( "\n", $textParts ), $align === 'left' ? 'start' : 'end' );
                continue;
            }

            // Parse wp:columns with image+text pattern before comments are stripped
            $results[] = $entry['type'] === 'columns' ? $this->parseColumnsBlock( $entry['html'] ) : $this->parseBlock( $block, $entry['type'] );
        }

        return $results;
    }


    /**
     * Converts the HTML blocks of classic content into content.
     *
     * @return array<int, array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null>
     */
    protected function htmlBlocks( string $html ): array
    {
        $results = [];

        foreach( $this->splitIntoBlocks( $html ) as $block )
        {
            if( !empty( $block = trim( $block ) ) ) {
                $results[] = $this->parseBlock( $block );
            }
        }

        return $results;
    }


    /**
     * Converts a text block into a text element with Markdown or an HTML element.
     *
     * If the HTML can be fully converted to Markdown, the "text" element is used,
     * otherwise the "html" element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    #[\Override]
    public function text( string $block ): ?array
    {
        $block = trim( $block );

        if( empty( $block ) ) {
            return null;
        }

        $markdown = Markdown::fromHtml( $block );

        if( !empty( $markdown ) && !$this->hasUnconvertibleHtml( $block ) ) {
            return ['elements' => [Elements::create( 'text', ['text' => $markdown] )]];
        }

        return ['elements' => [Elements::create( 'html', ['text' => Utils::html( $block )] )]];
    }


    /**
     * Cleans a code block by removing HTML tags and decoding entities.
     */
    protected function cleanCodeBlock( string $code ): string
    {
        $code = (string) preg_replace( '/<\/?code>/i', '', $code );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

        return trim( $code );
    }


    /**
     * Returns a single-file element (audio, image or video) with its file ID.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function fileElement( string $type, string $fileId ): array
    {
        return ['elements' => [Elements::create( $type, ['file' => Elements::file( $fileId )] )], 'fileIds' => [$fileId]];
    }


    /**
     * Checks if HTML contains tags that cannot be fully converted to markdown.
     *
     * Simple inline formatting (bold, italic, links, code, paragraphs, line breaks,
     * lists, blockquotes) is convertible. Anything else (tables, divs, figures,
     * iframes, etc.) is not.
     */
    protected function hasUnconvertibleHtml( string $html ): bool
    {
        $convertible = 'p|br|strong|b|em|i|a|code|ul|ol|li|blockquote|span|sup|sub|del|s|mark|abbr|hr';
        $stripped = preg_replace( '/<\/?(' . $convertible . ')(\s[^>]*)?\/?>/i', '', $html );

        return (bool) preg_match( '/<[a-z][a-z0-9]*[\s>\/]/i', $stripped ?? '' );
    }


    /**
     * Creates an image-text element from an imported image and HTML text.
     *
     * Falls back to an image element without text or to a text element without image.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function imageText( ?string $fileId, string $html, ?string $position = null ): array
    {
        $html = (string) preg_replace( '/<(figure|div|figcaption)[^>]*>|<\/(figure|div|figcaption)>/i', '', $html );
        $text = Markdown::fromHtml( $html );

        if( $fileId && !empty( $text ) )
        {
            $data = ['text' => $text, 'file' => Elements::file( $fileId )];

            return [
                'elements' => [Elements::create( 'image-text', $position ? $data + ['position' => $position] : $data )],
                'fileIds' => [$fileId],
            ];
        }

        if( $fileId ) {
            return $this->fileElement( 'image', $fileId );
        }

        return ['elements' => ( !empty( $text ) ? $this->text( $text ) : null )['elements'] ?? [], 'fileIds' => []];
    }


    /**
     * Merges consecutive text elements (same type) into a single element.
     *
     * Consecutive "text" elements are joined with double newline (markdown).
     * Consecutive "html" elements are concatenated directly.
     *
     * @param array<int, array<string, mixed>> $elements
     * @return array<int, array<string, mixed>>
     */
    protected function mergeConsecutiveText( array $elements ): array
    {
        $merged = [];

        foreach( $elements as $el )
        {
            $prev = end( $merged );

            if( $prev && $prev['type'] === $el['type']
                && in_array( $el['type'], ['text', 'html'] )
                && isset( $prev['data']['text'] ) && isset( $el['data']['text'] ) )
            {
                $sep = $el['type'] === 'text' ? "\n\n" : '';
                $merged[count( $merged ) - 1]['data']['text'] .= $sep . $el['data']['text'];
            }
            else
            {
                $merged[] = $el;
            }
        }

        return $merged;
    }


    /**
     * Parses an audio block into an audio element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseAudioBlock( string $block ): ?array
    {
        if( !preg_match( '/src=["\']([^"\']+)["\']/', $block, $m ) ) {
            return null;
        }

        $fileId = ( $this->file )( $m[1] );

        if( !$fileId ) {
            return null;
        }

        return $this->fileElement( 'audio', $fileId );
    }


    /**
     * Dispatches a single HTML block to the appropriate parser.
     *
     * @param string|null $wpType Gutenberg block type or NULL for classic content
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    #[\Override]
    protected function parseBlock( string $block, ?string $wpType = null ): ?array
    {
        if( $wpType !== null )
        {
            // Skip decorative/layout-only Gutenberg blocks
            if( in_array( $wpType, ['separator', 'spacer', 'buttons', 'button', 'columns', 'column', 'group', 'more'] ) ) {
                return null;
            }

            // Use Gutenberg type hints to map to Pagible schema types
            if( $wpType === 'heading' ) {
                return $this->parseHeadingBlock( $block ) ?? $this->text( $block );
            }

            if( in_array( $wpType, ['code', 'preformatted', 'syntaxhighlighter/code'] ) ) {
                return $this->parseCodeBlock( $block ) ?? $this->text( $block );
            }

            if( $wpType === 'image' ) {
                return $this->parseStandaloneImageBlock( $block ) ?? $this->parseImageTextBlock( $block );
            }

            if( $wpType === 'gallery' ) {
                return $this->parseGalleryBlock( $block ) ?? $this->parseStandaloneImageBlock( $block );
            }

            if( in_array( $wpType, ['media-text', 'cover'] ) ) {
                return $this->parseImageTextBlock( $block );
            }

            if( $wpType === 'table' ) {
                return $this->parseTableBlock( $block ) ?? $this->text( $block );
            }

            if( in_array( $wpType, ['video', 'core-embed/youtube', 'core-embed/vimeo', 'embed'] ) ) {
                return $this->parseVideoBlock( $block ) ?? $this->text( $block );
            }

            if( $wpType === 'audio' ) {
                return $this->parseAudioBlock( $block );
            }

            if( in_array( $wpType, ['paragraph', 'list', 'quote', 'pullquote', 'verse', 'freeform'] ) ) {
                if( preg_match( '/<img[^>]+>/i', $block ) ) {
                    return $this->parseImageTextBlock( $block );
                }
                return $this->text( $block );
            }
        }

        // Fall through to HTML-based detection for unknown/null types
        $result = $this->parseHeadingBlock( $block ) ?? $this->parseCodeBlock( $block )
            ?? $this->parseStandaloneImageBlock( $block ) ?? $this->parseNoticeBlock( $block );

        if( $result ) {
            return $result;
        }

        if( preg_match( '/<img[^>]+>/i', $block ) ) {
            return $this->parseImageTextBlock( $block );
        }

        return $this->text( $block );
    }


    /**
     * Parses a <pre> tag into a code element, the language is taken from the "lang" attribute.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    #[\Override]
    protected function parseCodeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<pre[^>]*>(.*?)<\/pre>$/is', $block, $m ) ) {
            return null;
        }

        $language = '';
        if( preg_match( '/lang=["\']?(\w+)/i', $block, $langMatch ) ) {
            $language = strtolower( $langMatch[1] );
        }

        return ['elements' => [Elements::create( 'code', [
            'language' => $language,
            'text' => $this->cleanCodeBlock( $m[1] ),
        ] )]];
    }


    /**
     * Parses a wp:columns block into an image-text element if it contains
     * one image column and one text column, otherwise processes inner content.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseColumnsBlock( string $block ): ?array
    {
        // Extract individual column contents using wp:column markers
        $columnPattern = '/<!--\s*wp:column(?:\s+' . self::ATTRS . ')?\s*-->(.*?)<!--\s*\/wp:column\s*-->/is';

        if( !preg_match_all( $columnPattern, $block, $columns ) ) {
            return null;
        }

        $imageCol = null;
        $imageColIndex = null;
        $textParts = [];

        foreach( $columns[1] as $i => $colContent )
        {
            $colContent = trim( $this->stripWpComments( $colContent ) );

            if( empty( $colContent ) ) {
                continue;
            }

            // Check if this column is primarily an image
            $stripped = strip_tags( $colContent, '<img><a>' );
            if( preg_match( '/^\s*(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?\s*$/is', $stripped ) && $imageCol === null ) {
                $imageCol = $colContent;
                $imageColIndex = $i;
            } else {
                $textParts[] = $colContent;
            }
        }

        // If we have exactly one image column and at least one text column, create image-text
        if( $imageCol !== null && !empty( $textParts ) )
        {
            // Image after text = end position, image before text = start position
            $position = $imageColIndex === 0 ? 'start' : 'end';
            $result = $this->imageText( $this->image( $imageCol ), implode( "\n", $textParts ), $position );

            if( ( $result['elements'][0]['type'] ?? null ) === 'image-text' ) {
                return $result;
            }
        }

        // Fallback: process all column content as individual blocks
        $elements = [];
        $fileIds = [];

        foreach( $columns[1] as $colContent )
        {
            // Unwrap the column div but keep the block comments so the inner block types survive
            $colContent = (string) preg_replace( '/^<div[^>]*>(.*)<\/div>$/is', '$1', trim( $colContent ) );

            foreach( $this->splitGutenbergBlocks( $colContent ) as $inner )
            {
                $innerHtml = trim( $this->stripWpComments( $inner['html'] ) );
                if( empty( $innerHtml ) ) {
                    continue;
                }

                if( $result = $this->parseBlock( $innerHtml, $inner['type'] ) ) {
                    array_push( $elements, ...$result['elements'] );
                    array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
                }
            }
        }

        if( !empty( $elements ) ) {
            return ['elements' => $elements, 'fileIds' => $fileIds];
        }

        return null;
    }


    /**
     * Parses a gallery block into a slideshow element with multiple images.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseGalleryBlock( string $block ): ?array
    {
        if( !preg_match_all( '/<img[^>]+>/i', $block, $matches ) || count( $matches[0] ) < 2 ) {
            return null;
        }

        $fileIds = [];

        foreach( $matches[0] as $imgTag )
        {
            $fileId = $this->image( $imgTag );
            if( $fileId ) {
                $fileIds[] = $fileId;
            }
        }

        if( count( $fileIds ) < 2 ) {
            return null;
        }

        return [
            'elements' => [Elements::create( 'slideshow', [
                'title' => '',
                'files' => array_map( fn( $id ) => Elements::file( $id ), $fileIds ),
            ] )],
            'fileIds' => $fileIds,
        ];
    }


    /**
     * Parses a text block containing an inline image into an image-text element.
     *
     * The text is converted to Markdown, texts without image are skipped.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    #[\Override]
    protected function parseImageTextBlock( string $html ): array
    {
        if( !preg_match( '/(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $html, $imgMatch ) ) {
            return ['elements' => [], 'fileIds' => []];
        }

        return $this->imageText( $this->image( $imgMatch[0] ), str_replace( $imgMatch[0], '', $html ) );
    }


    /**
     * Parses a tip/note/caution div into a styled text element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function parseNoticeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<div class="(tip|note|caution)">(.*?)<\/div>$/is', $block, $m ) ) {
            return null;
        }

        $label = $m[1] === 'caution' ? 'Warning' : ucfirst( $m[1] );

        return ['elements' => [Elements::create( 'text', ['text' => "**{$label}:** " . strip_tags( $m[2] )] )]];
    }


    /**
     * Parses a standalone image (or linked image) into an image element, wrapping tags are ignored.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    #[\Override]
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        $stripped = strip_tags( $block, '<img><a>' );
        $stripped = trim( $stripped );

        $isLinkedImage = preg_match( '/^<a[^>]*>\s*<img[^>]+>\s*<\/a>$/is', $stripped );
        $isBareImage = preg_match( '/^<img[^>]+\/>?$/is', $stripped );

        if( !$isLinkedImage && !$isBareImage ) {
            return null;
        }

        $fileId = $this->image( $block );

        if( !$fileId ) {
            return null;
        }

        return $this->fileElement( 'image', $fileId );
    }


    /**
     * Parses an HTML table into a table element.
     *
     * @return array{elements: array<int, array<string, mixed>>}|null
     */
    protected function parseTableBlock( string $block ): ?array
    {
        if( !preg_match( '/<table[^>]*>(.*?)<\/table>/is', $block, $m ) ) {
            return null;
        }

        $rows = [];
        $header = '';

        if( preg_match( '/<thead[^>]*>(.*?)<\/thead>/is', $m[1], $thead ) ) {
            $header = 'row';
            if( preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $thead[1], $cells ) ) {
                $rows[] = array_map( fn( $c ) => strip_tags( $c ), $cells[1] );
            }
        }

        if( preg_match( '/<tbody[^>]*>(.*?)<\/tbody>/is', $m[1], $tbody ) ) {
            $bodyHtml = $tbody[1];
        } else {
            $bodyHtml = $m[1];
        }

        if( preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $bodyHtml, $trs ) ) {
            foreach( $trs[1] as $tr ) {
                if( preg_match_all( '/<t[hd][^>]*>(.*?)<\/t[hd]>/is', $tr, $cells ) ) {
                    $rows[] = array_map( fn( $c ) => strip_tags( $c ), $cells[1] );
                }
            }
        }

        if( empty( $rows ) ) {
            return null;
        }

        $title = '';
        if( preg_match( '/<caption[^>]*>(.*?)<\/caption>/is', $m[1], $cap ) ) {
            $title = strip_tags( $cap[1] );
        }

        return ['elements' => [Elements::create( 'table', [
            'title' => $title,
            'header' => $header,
            'table' => $rows,
        ] )]];
    }


    /**
     * Parses a video block into a video element.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseVideoBlock( string $block ): ?array
    {
        $url = null;

        // Try <video> tag, then iframe/embed src attribute
        if( preg_match( '/<video[^>]+src=["\']([^"\']+)["\']/', $block, $m ) ) {
            $url = $m[1];
        } elseif( preg_match( '/<iframe[^>]+src=["\']([^"\']+)["\']/', $block, $m ) ) {
            $url = $m[1];
        }

        // Try plain-text URL (YouTube/Vimeo embeds have URL as text content)
        if( !$url && preg_match( '/https?:\/\/(?:(?:www\.)?youtube\.com\/watch\?v=|youtu\.be\/|(?:www\.)?vimeo\.com\/)[^\s<"]+/i', $block, $m ) ) {
            $url = $m[0];
        }

        if( $url && ( $fileId = ( $this->file )( $url ) ) ) {
            return $this->fileElement( 'video', $fileId );
        }

        return null;
    }


    /**
     * Returns TRUE if the HTML consists of tags without text or media only, e.g. the wrapping tags of a group.
     */
    protected function wrapper( string $html ): bool
    {
        return trim( strip_tags( $html ) ) === '' && !preg_match( '/<(?:img|video|audio|iframe|embed|object|hr|br)\b/i', $html );
    }


    /**
     * Splits Gutenberg content into blocks with their wp type hints.
     *
     * Parses `<!-- wp:type -->...<!-- /wp:type -->` and self-closing `<!-- wp:type /-->` comments.
     * Nested blocks are flattened — only top-level block types are used as hints, the blocks
     * inside of groups are top-level blocks too.
     *
     * @return array<int, array{type: string|null, attrs: array<mixed>, html: string}>
     */
    protected function splitGutenbergBlocks( string $html ): array
    {
        $blocks = [];
        $pattern = '/<!--\s*wp:([a-z][a-z0-9\-]*(?:\/[a-z][a-z0-9\-]*)?)(\s+' . self::ATTRS . ')?\s*(\/)?-->/is';
        $offset = 0;
        $length = strlen( $html );

        while( $offset < $length )
        {
            if( !preg_match( $pattern, $html, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
                // Remaining content after last block
                $remaining = trim( substr( $html, $offset ) );
                if( !empty( $remaining ) ) {
                    $blocks[] = ['type' => null, 'attrs' => [], 'html' => $remaining];
                }
                break;
            }

            $matchPos = (int) $m[0][1];

            // Content before this block comment (untyped)
            if( $matchPos > $offset ) {
                $before = trim( substr( $html, $offset, $matchPos - $offset ) );
                if( !empty( $before ) ) {
                    $blocks[] = ['type' => null, 'attrs' => [], 'html' => $before];
                }
            }

            $type = $m[1][0];
            $attrs = !empty( $m[2][0] ) ? (array) json_decode( trim( $m[2][0] ), true ) : [];
            $selfClosing = !empty( $m[3][0] );

            if( $selfClosing ) {
                // Self-closing: <!-- wp:type /-->
                $offset = $matchPos + strlen( $m[0][0] );
                continue; // No content to extract
            }

            // Find matching closing comment, tracking depth for nested blocks
            $searchPos = $matchPos + strlen( $m[0][0] );
            $depth = 1;
            $closeEnd = null;
            $contentEnd = $searchPos;
            $contentStart = $searchPos;

            while( $depth > 0 && preg_match( '/<!--\s*(\/)?wp:' . preg_quote( $type, '/' ) . '(?:\s+' . self::ATTRS . ')?\s*(\/)?-->/is', $html, $cm, PREG_OFFSET_CAPTURE, $searchPos ) )
            {
                $isClosing = !empty( $cm[1][0] );
                $isSelfClosing = !empty( $cm[2][0] );

                if( $isSelfClosing ) {
                    $searchPos = (int) $cm[0][1] + strlen( $cm[0][0] );
                    continue;
                }

                if( $isClosing ) {
                    $depth--;
                    if( $depth === 0 ) {
                        $closeEnd = (int) $cm[0][1] + strlen( $cm[0][0] );
                        $contentEnd = (int) $cm[0][1];
                    }
                } else {
                    $depth++;
                }

                $searchPos = (int) $cm[0][1] + strlen( $cm[0][0] );
            }

            if( $closeEnd !== null ) {
                $content = trim( substr( $html, $contentStart, $contentEnd - $contentStart ) );
                if( $type === 'group' ) {
                    // groups only wrap other blocks, so the inner blocks are used without the wrapping tags
                    $inner = $this->splitGutenbergBlocks( $content );
                    array_push( $blocks, ...array_filter( $inner, fn( $block ) => $block['type'] !== null || !$this->wrapper( $block['html'] ) ) );
                } elseif( !empty( $content ) ) {
                    $blocks[] = ['type' => $type, 'attrs' => $attrs, 'html' => $content];
                }
                $offset = $closeEnd;
            } else {
                // No matching close found — skip the opening comment
                $offset = $matchPos + strlen( $m[0][0] );
            }
        }

        return $blocks;
    }


    /**
     * Splits classic content into logical blocks at heading, pre/notice div, image and paragraph boundaries.
     *
     * @return string[]
     */
    #[\Override]
    protected function splitIntoBlocks( string $html ): array
    {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>|<pre[^>]*>.*?<\/pre>|<div\s+class="(?:tip|note|caution)"[^>]*>.*?<\/div>)/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

        $blocks = [];

        foreach( $parts as $part )
        {
            $part = trim( $part );
            if( empty( $part ) ) {
                continue;
            }

            if( preg_match( '/^<(h[1-6]|pre|div\s+class="(?:tip|note|caution)")/i', $part ) ) {
                $blocks[] = $part;
                continue;
            }

            $subParts = preg_split( '/(\n\s*\n|(?:<a[^>]*>\s*<img[^>]+>\s*<\/a>)|(?:<img[^>]+\/?>))/is', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

            foreach( $subParts as $sub ) {
                $sub = trim( $sub );
                if( !empty( $sub ) ) {
                    $blocks[] = $sub;
                }
            }
        }

        return $blocks;
    }


    /**
     * Strips all WordPress block comments from HTML.
     */
    protected function stripWpComments( string $html ): string
    {
        return trim( (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/i', '', $html ) );
    }
}
