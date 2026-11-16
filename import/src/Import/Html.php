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
    /** Regular expression fragment matching an image tag, usable with "/" and "#" as delimiters */
    protected const IMAGE_TAG = '<img\b[^>]*>';
    /** Regular expression matching image tags */
    protected const IMAGE_PATTERN = '/' . self::IMAGE_TAG . '/i';
    /** Regular expression matching an image tag with the link around it if there's one, the opening link tag is the first group */
    protected const LINKED_IMAGE_PATTERN = '/(?:(<a\b[^>]*>)\s*)?' . self::IMAGE_TAG . '(?(1)\s*<\/a>)/i';
    /**
     * Regular expression matching the tags of figures, which aren't part of the text next to an image, captions become paragraphs instead.
     *
     * Other tags like <div> inside of figures are kept because they may structure the text, the Markdown conversion removes them anyway.
     */
    protected const FIGURE_TAGS_PATTERN = '/<\/?figure\b[^>]*>/i';
    /** Regular expression matching block structure which is lost if the text next to an image is converted to Markdown */
    protected const STRUCTURE_PATTERN = '/<(?:table|blockquote|ul|ol|dl|pre)\b/i';
    /** Regex patterns of the elements which are kept as separate blocks by all importers, subclasses add their embedded media like iframes, see mediaTags() */
    protected const BLOCK_TAGS = ['h[1-6]', 'pre', 'figure'];
    /** Regex pattern of the tag names of lists and tables, which are matched including the ones nested in them, see fragments() */
    protected const CONTAINER_TAGS = 'ul|ol|dl|table';
    /**
     * Regex pattern of the block tags removed by text() if they are empty at the start or end of the block, also if they have attributes.
     *
     * They are left over from splitting paragraphs and containers around images or media, e.g. "<p class="center">" of
     * "<p class="center"><img src="a.jpg"></p>". Table cells and list items aren't removed because they are part
     * of the structure of their tables and lists.
     */
    protected const EMPTY_BLOCK_TAGS = 'p|div';
    /**
     * Regex pattern of the inline tags removed by text() if they are empty at the start or end of the block and have no attributes.
     *
     * Empty inline tags with attributes are kept because they may be icons like <i class="fa fa-check"></i> or links.
     */
    protected const EMPTY_TAGS = 'span|strong|em|b|i|u|s|small|a';
    /** Attributes of image tags containing the source URL, the ones of lazy loaded images first because "src" is a placeholder then, "src" must be last, see source() */
    protected const SOURCE_ATTRIBUTES = ['data-src', 'data-lazy-src', 'data-original', 'src'];


    /** @var (\Closure(string): void)|null Shows a warning, e.g. if processing the HTML failed, ignored if NULL */
    protected ?\Closure $warn = null;


    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     */
    public function __construct( protected \Closure $image )
    {
    }


    /**
     * Returns the raw value of the attribute of the HTML tag.
     *
     * Only whole attribute names are matched, e.g. "src" doesn't match "data-src".
     *
     * @param string $tag HTML tag like <img src="a.jpg" alt="Image">
     * @param string $name Name of the attribute like "alt"
     * @return string Value of the attribute with its HTML entities or an empty string if there's none
     */
    public static function attribute( string $tag, string $name ): string
    {
        return preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match ) ? $match[2] : '';
    }


    /**
     * Returns the value of the attribute of the HTML tag with its HTML entities decoded.
     *
     * @param string $tag HTML tag like <img src="a.jpg" alt="A &amp; B">
     * @param string $name Name of the attribute like "alt"
     * @return string Decoded value of the attribute like "A & B" or an empty string if there's none
     */
    public static function decodedAttribute( string $tag, string $name ): string
    {
        return self::decode( self::attribute( $tag, $name ) );
    }


    /**
     * Decodes the HTML entities of a text like the value of an attribute.
     *
     * @param string $text Text with HTML entities like "A &amp; B"
     * @return string Decoded text like "A & B"
     */
    public static function decode( string $text ): string
    {
        return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }


    /**
     * Returns the raw source URL and alternative text of the HTML image tag.
     *
     * Lazy loaded images contain the real source in the "data-src", "data-lazy-src" or "data-original" attribute
     * and a placeholder or nothing in "src", so these attributes are preferred if they aren't empty. Placeholders
     * can be in the lazy load attributes too, so values with "data:" URIs are skipped unless "src" contains one
     * as well. Then, the "data:" URI of "src" is returned, so the importers can report the inline image.
     *
     * The image closures get the image with the link, figure or block around it, so only the attributes of the first
     * image tag are used. Otherwise, lazy load attributes of the link (e.g. data-src="#gallery" of lightboxes) or of
     * tags in the caption would be preferred over the source of the image.
     *
     * @param string $tag HTML image tag, e.g. "<img src="photo.jpg" alt="Photo">" or "<img data-src="photo.jpg" src="placeholder.gif">",
     *  or HTML containing it like "<a href="big.jpg" data-src="#gallery"><img src="photo.jpg"></a>"
     * @return array{src: string, alt: string}|null Source and alternative text with their HTML entities or NULL if the tag has no source
     */
    public static function source( string $tag ): ?array
    {
        $src = '';
        $tag = preg_match( self::IMAGE_PATTERN, $tag, $match ) ? $match[0] : $tag;

        foreach( self::SOURCE_ATTRIBUTES as $name )
        {
            $src = self::attribute( $tag, $name );

            if( ( $value = ltrim( $src ) ) !== '' && strncasecmp( $value, 'data:', 5 ) !== 0 ) {
                return ['src' => $src, 'alt' => self::attribute( $tag, 'alt' )];
            }
        }

        // "src" is the last attribute, so its "data:" URI is left as fallback for reporting the inline image
        return trim( $src ) !== '' ? ['src' => $src, 'alt' => self::attribute( $tag, 'alt' )] : null;
    }


    /**
     * Returns the source URL and alternative text of the HTML image tag with their HTML entities decoded.
     *
     * @param string $tag HTML image tag, e.g. "<img src="photo.jpg?w=1&amp;h=2" alt="Photo">"
     * @return array{src: string, alt: string}|null Decoded source and alternative text or NULL if the tag has no source
     */
    public static function decodedSource( string $tag ): ?array
    {
        if( !( $source = self::source( $tag ) ) ) {
            return null;
        }

        return ['src' => self::decode( $source['src'] ), 'alt' => self::decode( $source['alt'] )];
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
        // empty tags left over from splitting paragraphs and containers around images or media, e.g. "<p>" of "<p>A</p><p><img></p>",
        // removed until none is left because removing nested ones leaves their parents empty, e.g. "<div><p></p></div>"
        $empty = '<(?|(' . self::EMPTY_BLOCK_TAGS . ')\b[^>]*|(' . self::EMPTY_TAGS . ')\s*)>\s*<\/\%d\s*>';
        $patterns = [
            // empty tags preceded by opening tags only, e.g. "<span>" in "<p><span></span>Text</p>"
            '/^((?:\s*<[a-z][^>]*>)*?)\s*' . sprintf( $empty, 2 ) . '/i',
            // empty tags followed by closing tags only, e.g. "<p>" in "<div>Text<p></p></div>"
            '/' . sprintf( $empty, 1 ) . '((?:\s*<\/[a-z][a-z0-9]*\s*>)*)\s*$/i',
        ];
        $html = trim( Utils::html( trim( $block ) ) );

        do {
            $html = trim( $this->result( preg_replace( $patterns, ['$1', '$2'], $prev = $html ), $prev ) );
        } while( $html !== $prev );

        if( $html === '' ) {
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
        return [...self::BLOCK_TAGS, ...$this->mediaTags()];
    }


    /**
     * Returns the regex patterns of the embedded media like iframes which are kept as separate blocks.
     *
     * Figures containing them are split into their media, images and captions, see splitFigure().
     *
     * @return string[] Regex patterns of the tag names, none by default because the HTML sanitizer removes embedded media
     */
    protected function mediaTags(): array
    {
        return [];
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

        if( $result = $this->parseContainerBlock( $block ) ) {
            return $result;
        }

        // standalone images whose import failed are not imported again as images with text
        if( $this->standaloneImage( $block ) ) {
            return $this->parseStandaloneImageBlock( $block );
        }

        if( preg_match( self::IMAGE_PATTERN, $block ) ) {
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
        $code = self::decode( $code );

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
            'title' => self::decode( strip_tags( $matches[2] ) ),
        ] )]];
    }


    /**
     * Parses a text block containing an inline image.
     *
     * The image and the link around it become an image-text element with the remaining HTML converted to Markdown.
     * If the image can't be imported, the remaining HTML becomes a text element and the link is kept with the
     * alternative text of the image, see unimported().
     *
     * If the remaining HTML contains block structure like tables, quotes, lists or preformatted text, e.g. in
     * figures, Markdown would lose it. Then, the image becomes an image element followed by an HTML element with
     * the remaining HTML, which is less lossy. Further images, e.g. of figures with several images, become
     * separate image elements after these elements like images outside of figures, see furtherImages(). They
     * are removed from the remaining HTML including the links around them if they contain nothing else because
     * the images of the HTML element wouldn't be imported and the links would be empty, see removeImages().
     *
     * Image-text elements have no link, so the link target of a linked image is lost.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    protected function parseImageTextBlock( string $html ): array
    {
        if( !preg_match( self::LINKED_IMAGE_PATTERN, $html, $matches ) ) {
            return Elements::content( ...$this->text( $html )['elements'] ?? [] );
        }

        if( !( $fileId = $this->image( $matches[0] ) ) ) {
            return $this->unimported( $html, $matches[0] );
        }

        $html = $this->unfigure( $this->replaceFirst( $html, $matches[0], '' ) );
        $rest = $this->removeImages( $html );

        if( preg_match( self::STRUCTURE_PATTERN, $rest ) ) {
            return Elements::merge( Elements::imageContent( $fileId ), $this->text( $rest ), $this->furtherImages( $html ) );
        }

        return Elements::merge( Elements::imageContent( $fileId, Markdown::fromHtml( $rest ) ), $this->furtherImages( $html ) );
    }


    /**
     * Parses a list or table containing images.
     *
     * The images aren't split from the list or table because that would break its structure, e.g. the custom fields
     * of Joomla articles rendered as list. HTML elements can't refer to imported files, so the list or table becomes
     * an HTML element without its images and the images become separate image elements after it like the further
     * images of figures, see furtherImages(). The links around the images are removed if they contain nothing else.
     *
     * @param string $block HTML block like "<ul><li>Photo: <img src="a.jpg"></li></ul>"
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null Content or NULL if it's no list or table with images
     */
    protected function parseContainerBlock( string $block ): ?array
    {
        $fragments = $this->fragments( $block, ['container' => [self::CONTAINER_TAGS, true]] );

        if( count( $fragments ) !== 1 || $fragments[0][0] !== 0 || $fragments[0][1] !== strlen( $block ) || !preg_match( self::IMAGE_PATTERN, $block ) ) {
            return null;
        }

        return Elements::merge( $this->text( $this->removeImages( $block ) ), $this->furtherImages( $block ) );
    }


    /**
     * Imports the images of the HTML as separate image elements in their order.
     *
     * The links around the images are lost like the ones of image-text elements and images
     * which can't be imported are dropped.
     *
     * @param string $html HTML whose first image has already been imported or removed
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} Content with the image elements
     */
    protected function furtherImages( string $html ): array
    {
        preg_match_all( self::LINKED_IMAGE_PATTERN, $html, $matches );
        return Elements::merge( ...array_map( fn( $image ) => Elements::imageContent( $this->image( $image ) ), $matches[0] ) );
    }


    /**
     * Parses a standalone image into an image element.
     *
     * The caption of figures is the text of an image-text element. If the image can't be imported, the remaining
     * HTML is kept like in parseImageTextBlock().
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null Content or NULL if it's no standalone image
     */
    protected function parseStandaloneImageBlock( string $block ): ?array
    {
        if( !$this->standaloneImage( $block ) ) {
            return null;
        }

        if( !( $fileId = $this->image( $block ) ) ) {
            return $this->unimported( $block, preg_match( self::LINKED_IMAGE_PATTERN, $block, $match ) ? $match[0] : '' );
        }

        $caption = preg_match( '/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $block, $match ) ? Markdown::fromHtml( $match[1] ) : '';

        return Elements::imageContent( $fileId, $caption );
    }


    /**
     * Tests if the block is a standalone image, i.e. an image, a linked image or a figure with an image.
     *
     * Figures are only standalone images if they contain nothing else than the image, the link around it and
     * the caption after it. Figures with other content are images with text, see parseImageTextBlock().
     */
    protected function standaloneImage( string $block ): bool
    {
        $image = '(?:<a\b[^>]*>\s*' . self::IMAGE_TAG . '\s*<\/a>|' . self::IMAGE_TAG . ')';

        return preg_match( '/^' . $image . '$/is', $block )
            || preg_match( '/^<figure\b[^>]*>\s*' . $image . '\s*(?:<figcaption\b[^>]*>(?:(?!<\/?figure\b|<img\b).)*?<\/figcaption>\s*)?<\/figure>$/is', $block );
    }


    /**
     * Returns the content of the HTML whose image couldn't be imported.
     *
     * The image is removed but the link around it is kept with the alternative text of the image as link text.
     * Links of images without alternative text are removed too because they would be empty. Captions of figures
     * become paragraphs and links at the start of figures are paragraphs too, so they aren't glued together.
     * Further images become separate image elements after the text element like in parseImageTextBlock().
     *
     * @param string $html HTML containing the image
     * @param string $image Image tag with the link around it if there's one
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>} Content with the text element or without elements
     */
    protected function unimported( string $html, string $image ): array
    {
        $text = '';

        if( preg_match( '/^(<a\b[^>]*>)/i', $image, $link ) && preg_match( self::IMAGE_PATTERN, $image, $tag )
            && ( $alt = trim( self::decodedAttribute( $tag[0], 'alt' ) ) ) !== ''
        ) {
            $text = $link[1] . htmlspecialchars( $alt, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '</a>';
        }

        if( $text !== '' && ( $pos = strpos( $html, $image ) ) !== false && preg_match( '/^\s*<figure\b[^>]*>\s*$/i', substr( $html, 0, $pos ) ) ) {
            $text = '<p>' . $text . '</p>';
        }

        $html = $this->unfigure( $image !== '' ? $this->replaceFirst( $html, $image, $text ) : $html );
        $rest = trim( $this->removeImages( $html ) );

        return Elements::merge( $rest !== '' ? $this->text( $rest ) : null, $this->furtherImages( $html ) );
    }


    /**
     * Removes the images and the links around them if the links contain nothing else.
     *
     * Links containing text besides the image are kept with their text.
     *
     * @param string $html HTML like "<p>Text <a href="/page"><img src="a.jpg"></a></p>"
     * @return string HTML without images like "<p>Text </p>"
     */
    protected function removeImages( string $html ): string
    {
        return $this->result( preg_replace( self::LINKED_IMAGE_PATTERN, '', $html ), $html );
    }


    /**
     * Returns the HTML with the first occurrence of the search string replaced.
     *
     * @param string $html HTML containing the search string
     * @param string $search String to replace, e.g. an image tag
     * @param string $replace Replacement string
     * @return string HTML with the replacement or the unchanged HTML if the search string isn't found
     */
    protected function replaceFirst( string $html, string $search, string $replace ): string
    {
        $pos = $search !== '' ? strpos( $html, $search ) : false;
        return $pos !== false ? substr_replace( $html, $replace, $pos, strlen( $search ) ) : $html;
    }


    /**
     * Removes the tags of figures and converts their captions to paragraphs.
     *
     * The captions are separate paragraphs, so they aren't glued to the text or links before or after them.
     *
     * @param string $html HTML of a figure, e.g. "<figure><table>...</table><figcaption>Caption</figcaption></figure>"
     * @return string HTML without figure tags, e.g. "<table>...</table><p>Caption</p>"
     */
    protected function unfigure( string $html ): string
    {
        $html = $this->result( preg_replace( ['/<figcaption\b[^>]*>/i', '/<\/figcaption\s*>/i'], ['<p>', '</p>'], $html ), $html );
        return $this->result( preg_replace( self::FIGURE_TAGS_PATTERN, '', $html ), $html );
    }


    /**
     * Tests if the figure is split into blocks again instead of being a standalone image or an image with text.
     *
     * Figures with embedded media like iframes of the subclasses would lose their media because the HTML sanitizer
     * removes them, figures without images would lose their structure because the sanitizer removes the figure
     * and caption tags and nested figures would be glued together. Figures containing images and nothing else
     * than text are kept, see parseStandaloneImageBlock() and parseImageTextBlock().
     *
     * @param string $block HTML block like "<figure><iframe src="..."></iframe><figcaption>Video</figcaption></figure>"
     */
    protected function splitFigure( string $block ): bool
    {
        if( !preg_match( '/^<figure\b[^>]*>(.*)$/is', $block, $match ) ) {
            return false;
        }

        $media = $this->mediaTags();

        return !preg_match( self::IMAGE_PATTERN, $block ) || preg_match( '/<figure\b/i', $match[1] )
            || $media !== [] && preg_match( '/<(?:' . implode( '|', $media ) . ')\b/i', $block );
    }


    /**
     * Removes the tags of the figure and converts its caption to a paragraph, the figures nested in it are kept.
     *
     * The caption paragraph stays at the position of the caption, so captions after the content are paragraphs after it.
     *
     * @param string $block HTML of the figure, e.g. "<figure><figure><img src="a.jpg"></figure><figcaption>Gallery</figcaption></figure>"
     * @return string HTML of the content, e.g. "<figure><img src="a.jpg"></figure><p>Gallery</p>"
     */
    protected function unwrapFigure( string $block ): string
    {
        $content = $this->result( preg_replace( ['/^<figure\b[^>]*>/i', '/<\/figure\s*>$/i'], '', $block ), $block );
        $html = '';
        $pos = 0;

        foreach( $this->fragments( $content, ['figure' => ['figure', true]] ) as [$offset, $length] )
        {
            $html .= $this->unfigure( substr( $content, $pos, $offset - $pos ) ) . substr( $content, $offset, $length );
            $pos = $offset + $length;
        }

        return $html . $this->unfigure( substr( $content, $pos ) );
    }


    /**
     * Splits HTML content into logical blocks.
     *
     * Headings, preformatted text, figures and embedded media are separate blocks, the rest is split at blank lines
     * and images, see splitText(). Figures are matched including the figures nested in them and split into blocks
     * again if necessary, see splitFigure().
     *
     * Lists and tables are kept intact including the headings and preformatted text in them. Figures with images or
     * media and the media in lists and tables become separate blocks after them like images, see unnest().
     *
     * @return string[]
     */
    protected function splitIntoBlocks( string $html ): array
    {
        $groups = ['container' => [self::CONTAINER_TAGS, true]];

        foreach( $this->blockTags() as $tag ) {
            $groups[$tag] = [$tag, $tag === 'figure'];
        }

        $blocks = [];
        $text = '';
        $pos = 0;

        foreach( $this->fragments( $html, $groups ) as [$offset, $length, $group] )
        {
            $fragment = substr( $html, $offset, $length );
            $text .= substr( $html, $pos, $offset - $pos );
            $pos = $offset + $length;
            $moved = [$fragment];

            // lists and tables without figures and media stay in the text around them like in splitText()
            if( $group === 'container' )
            {
                [$container, $moved] = $this->unnest( $fragment );
                $text .= $container;

                if( $moved === [] ) {
                    continue;
                }
            }

            array_push( $blocks, ...$this->splitText( $text ) );
            $text = '';

            foreach( $moved as $block )
            {
                // unchanged if unwrapping failed, which would split the same figure again endlessly
                if( $this->splitFigure( $block ) && ( $content = $this->unwrapFigure( $block ) ) !== $block ) {
                    array_push( $blocks, ...$this->splitIntoBlocks( $content ) );
                } else {
                    $blocks[] = trim( $block );
                }
            }
        }

        array_push( $blocks, ...$this->splitText( $text . substr( $html, $pos ) ) );

        return $blocks;
    }


    /**
     * Splits HTML without headings, figures and media into blocks at blank lines and images.
     *
     * Lists and tables aren't split because that would break their structure. The ones containing images
     * are separate blocks, see parseContainerBlock(). The text after an image inside of a paragraph is
     * wrapped in the opening tag of the paragraph again, so it doesn't lose its paragraph.
     *
     * @param string $html HTML like "<p>Text</p><p><img src="a.jpg"></p><ul><li><img src="b.jpg"></li></ul>"
     * @return string[] Blocks like "<p>Text</p><p>", "<img src="a.jpg">", "<p></p>" and "<ul><li><img src="b.jpg"></li></ul>"
     */
    protected function splitText( string $html ): array
    {
        $containers = [];
        $text = '';
        $pos = 0;

        foreach( $this->fragments( $html, ['container' => [self::CONTAINER_TAGS, true]] ) as [$offset, $length] )
        {
            $container = substr( $html, $offset, $length );
            // lists and tables with images are split from the text around them, the other ones are kept in it
            $char = preg_match( self::IMAGE_PATTERN, $container ) ? "\x01" : "\x02";
            $text .= substr( $html, $pos, $offset - $pos ) . $char . count( $containers ) . $char;
            $containers[] = $container;
            $pos = $offset + $length;
        }

        $text .= substr( $html, $pos );
        $image = '(?:<a\b[^>]*>\s*' . self::IMAGE_TAG . '\s*<\/a>|' . self::IMAGE_TAG . ')';
        $parts = preg_split( '/(\n\s*\n|\x01\d+\x01|' . $image . ')/is', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );

        if( $parts === false ) {
            $this->failed();
            $parts = [$text];
        }

        $blocks = [];
        $paragraph = '';
        $afterImage = false;

        foreach( $parts as $part )
        {
            // blank lines and lists or tables end the paragraph
            if( trim( $part ) === '' || preg_match( '/^\x01\d+\x01$/', $part ) ) {
                $paragraph = '';
                $afterImage = false;
            }
            elseif( preg_match( '/^' . $image . '$/is', $part ) ) {
                $afterImage = true;
            }
            else
            {
                $part = $afterImage && $paragraph !== '' ? $paragraph . ltrim( $part ) : $part;
                $paragraph = $this->unclosed( $part );
                $afterImage = false;
            }

            $block = preg_replace_callback( '/[\x01\x02](\d+)[\x01\x02]/', fn( $match ) => $containers[(int) $match[1]], $part );

            if( ( $block = trim( $this->result( $block, $part ) ) ) !== '' ) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }


    /**
     * Returns the opening paragraph tag at the end of the HTML which isn't closed.
     *
     * @param string $html HTML like "<p>Text</p><p class="x">Text "
     * @return string Opening paragraph tag like "<p class="x">" or an empty string if all paragraphs are closed
     */
    protected function unclosed( string $html ): string
    {
        if( !preg_match_all( '/<(\/?)p\b[^>]*>/i', $html, $matches, PREG_SET_ORDER ) ) {
            return '';
        }

        $last = end( $matches );
        return $last[1] === '' ? $last[0] : '';
    }


    /**
     * Separates the figures with images or media and the media from the list or table.
     *
     * Headings, preformatted text and figures without images or media stay in the list or table because they are
     * part of its structure. The moved figures and media become blocks after the list or table like its images,
     * see parseContainerBlock().
     *
     * @param string $container HTML of the list or table like "<table><tr><td><iframe src="..."></iframe></td><td>Text</td></tr></table>"
     * @return array{0: string, 1: list<string>} HTML of the list or table without the moved figures and media and the moved ones in their order
     */
    protected function unnest( string $container ): array
    {
        $media = $this->mediaTags();
        $groups = ['figure' => ['figure', true], 'pre' => ['pre', false]];

        foreach( $media as $tag ) {
            $groups[$tag] = [$tag, false];
        }

        $mediaPattern = $media !== [] ? '/<(?:' . implode( '|', $media ) . ')\b/i' : null;
        $html = '';
        $moved = [];
        $pos = 0;

        foreach( $this->fragments( $container, $groups ) as [$offset, $length, $group] )
        {
            $fragment = substr( $container, $offset, $length );

            if( $group === 'pre' || $group === 'figure' && !preg_match( self::IMAGE_PATTERN, $fragment )
                && ( $mediaPattern === null || !preg_match( $mediaPattern, $fragment ) )
            ) {
                continue;
            }

            $html .= substr( $container, $pos, $offset - $pos );
            $moved[] = $fragment;
            $pos = $offset + $length;
        }

        return [$html . substr( $container, $pos ), $moved];
    }


    /**
     * Returns the positions of the outermost elements of the tags in the HTML.
     *
     * The HTML is scanned once for the opening and closing tags, so unclosed tags don't cause repeated scans
     * like recursive regular expressions. Elements of balanced groups include the nested elements of the same
     * group, e.g. lists in lists, the other ones end at the first closing tag of their group like a non-greedy
     * regular expression. Unclosed tags aren't elements but the elements inside them are. Elements inside of
     * found elements are skipped, so the first element wins like in a regular expression alternation.
     *
     * @param string $html HTML like "<p>A</p><ul><li><ul><li>B</li></ul></li></ul>"
     * @param array<string, array{0: string, 1: bool}> $groups Regex patterns of the tag names like "ul|ol" and if they are balanced by group name
     * @return list<array{0: int, 1: int, 2: string}> Byte offset, length and group name of the elements in their order
     */
    protected function fragments( string $html, array $groups ): array
    {
        $names = array_keys( $groups );
        $alternatives = [];

        foreach( $names as $idx => $name ) {
            $alternatives[] = '(?<g' . $idx . '>' . $groups[$name][0] . ')';
        }

        $pattern = '/<(\/?)(?:' . implode( '|', $alternatives ) . ')\b[^>]*>/i';

        if( preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL ) === false ) {
            $this->failed();
            return [];
        }

        $tokens = []; // group name, closing tag, start and end offset

        foreach( $matches as $match )
        {
            foreach( $names as $idx => $name )
            {
                if( isset( $match['g' . $idx][0] ) )
                {
                    $tokens[] = [$name, $match[1][0] === '/', (int) $match[0][1], (int) $match[0][1] + strlen( (string) $match[0][0] )];
                    break;
                }
            }
        }

        $ends = []; // index of the opening tag => index of its closing tag
        $stacks = [];
        $next = [];

        foreach( $tokens as $idx => [$name, $closing] )
        {
            if( !$groups[$name][1] ) {
                continue;
            }

            if( !$closing ) {
                $stacks[$name][] = $idx;
            } elseif( !empty( $stacks[$name] ) ) {
                $ends[array_pop( $stacks[$name] )] = $idx;
            }
        }

        for( $idx = count( $tokens ) - 1; $idx >= 0; $idx-- )
        {
            [$name, $closing] = $tokens[$idx];

            if( $groups[$name][1] ) {
                continue;
            }

            if( $closing ) {
                $next[$name] = $idx;
            } elseif( isset( $next[$name] ) ) {
                $ends[$idx] = $next[$name];
            }
        }

        $fragments = [];

        for( $idx = 0, $count = count( $tokens ); $idx < $count; $idx++ )
        {
            if( !$tokens[$idx][1] && isset( $ends[$idx] ) )
            {
                $end = $ends[$idx];
                $fragments[] = [$tokens[$idx][2], $tokens[$end][3] - $tokens[$idx][2], $tokens[$idx][0]];
                $idx = $end;
            }
        }

        return $fragments;
    }


    /**
     * Returns the result of a PCRE function or the fallback if the function failed, e.g. at the backtracking limit.
     *
     * @param string|null $result Result of the PCRE function, NULL on failure
     * @param string $fallback Value returned on failure, usually the unchanged input
     * @return string Result or fallback
     */
    protected function result( ?string $result, string $fallback ): string
    {
        if( $result === null )
        {
            $this->failed();
            return $fallback;
        }

        return $result;
    }


    /**
     * Shows a warning about the failed PCRE function if a warning closure is available.
     */
    protected function failed(): void
    {
        if( $this->warn ) {
            ( $this->warn )( 'Processing HTML failed, content kept unchanged: ' . preg_last_error_msg() );
        }
    }
}
