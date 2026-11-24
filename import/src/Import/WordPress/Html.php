<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Embeds;
use Aimeos\Cms\Import\Html as BaseHtml;
use Aimeos\Cms\Import\Markdown;


/**
 * Converts the content of WordPress posts into Pagible content elements.
 *
 * Gutenberg block comments are used as type hints, classic content is split at headings,
 * preformatted texts, notices, images and empty lines. Texts which can be converted to
 * Markdown become text elements, all others are kept as HTML.
 *
 * @phpstan-import-type ContentElements from Elements
 */
class Html extends BaseHtml
{
    /** JSON attributes of a block comment, which may contain nested objects but not the end of the comment */
    private const ATTRS = '\{(?:(?!-->).)*?\}';
    /** Block names with optional namespace like parse_blocks() of WordPress, "core/" is omitted in the comments */
    private const NAME = '[a-z][a-z0-9_\-]*(?:\/[a-z][a-z0-9_\-]*)?';
    /** Regular expression for URLs on their own line or in their own paragraph which WordPress embeds like WP_Embed::autoembed() */
    private const AUTOEMBED = '/(?|^[ \t]*(https?:\/\/[^\s<>"]+)[ \t]*$|<p(?: [^>]*)?>\s*(https?:\/\/[^\s<>"]+)\s*<\/p>)/im';
    /** Regular expression for the URLs of YouTube and Vimeo videos including mobile, short, live and player URLs */
    private const VIDEO = '#^https?://(?:(?:www\.|m\.)?youtube\.com/(?:watch\?|embed/|v/|shorts/|live/)|youtu\.be/|(?:www\.|player\.)?vimeo\.com/)#i';
    /** Block tags around which wpautop() doesn't add line breaks */
    private const AUTOP_BLOCKS = 'table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary';
    /** Tags removed by the HTML sanitizer whose content is lost, e.g. forms and scripts of Custom HTML blocks */
    private const REMOVED_TAGS = 'script|style|form|input|select|textarea|button|object|embed|svg|canvas|noscript';
    /** Maximum width in pixels of images shown within the text like icons */
    private const INLINE_WIDTH = 150;
    /** Names of the services of social links without label like block_core_social_link_services(), others are "Share Icon" */
    private const SOCIAL_NAMES = [
        'fivehundredpx' => '500px', 'amazon' => 'Amazon', 'bandcamp' => 'Bandcamp', 'behance' => 'Behance', 'bluesky' => 'Bluesky',
        'chain' => 'Link', 'codepen' => 'CodePen', 'deviantart' => 'DeviantArt', 'discord' => 'Discord', 'dribbble' => 'Dribbble',
        'dropbox' => 'Dropbox', 'etsy' => 'Etsy', 'facebook' => 'Facebook', 'feed' => 'RSS Feed', 'flickr' => 'Flickr',
        'foursquare' => 'Foursquare', 'goodreads' => 'Goodreads', 'google' => 'Google', 'github' => 'GitHub', 'gravatar' => 'Gravatar',
        'instagram' => 'Instagram', 'lastfm' => 'Last.fm', 'linkedin' => 'LinkedIn', 'mail' => 'Mail', 'mastodon' => 'Mastodon',
        'meetup' => 'Meetup', 'medium' => 'Medium', 'patreon' => 'Patreon', 'pinterest' => 'Pinterest', 'pocket' => 'Pocket',
        'reddit' => 'Reddit', 'share' => 'Share Icon', 'skype' => 'Skype', 'snapchat' => 'Snapchat', 'soundcloud' => 'SoundCloud',
        'spotify' => 'Spotify', 'telegram' => 'Telegram', 'threads' => 'Threads', 'tiktok' => 'TikTok', 'tumblr' => 'Tumblr',
        'twitch' => 'Twitch', 'twitter' => 'Twitter', 'vimeo' => 'Vimeo', 'vk' => 'VK', 'wordpress' => 'WordPress',
        'whatsapp' => 'WhatsApp', 'x' => 'X', 'yelp' => 'Yelp', 'youtube' => 'YouTube',
    ];

    /** @var array<string, string> Footnotes of the current post by their ID in the order of their numbers */
    protected array $footnotes = [];
    /** TRUE if the footnotes of the current post have been added by the footnotes block */
    protected bool $footnotesAdded = false;
    /** @var array<string, string> IDs of the elements by the HTML anchors of the blocks in the current post */
    protected array $anchors = [];
    /** ID of the current post for the element IDs of the blocks with HTML anchors, random IDs are used if NULL */
    protected int|string|null $postId = null;
    /** TRUE if the current post is classic content, whose single newlines are line breaks like by wpautop() */
    protected bool $classic = false;

    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $image Imports the image of the HTML tag and returns the file ID
     * @param \Closure(string, string): ?string $file Imports the audio, video or other file of the URL with the name (empty for the file name of the URL) and returns the file ID
     * @param (\Closure(string): void)|null $warn Shows a warning, e.g. if processing the HTML failed, ignored if NULL
     * @param (\Closure(string): ?string)|null $src Returns the URL of the stored file by its ID for images shown within the text, they aren't imported if NULL
     */
    public function __construct( \Closure $image, protected \Closure $file, ?\Closure $warn = null, protected ?\Closure $src = null )
    {
        parent::__construct( $image, $warn );
    }


    /**
     * Converts the WordPress post content into Pagible content elements.
     *
     * @param array<string, string> $footnotes HTML texts of the footnotes by their ID from the "footnotes" post meta
     * @param int|string|null $postId ID of the post for the element IDs of blocks with HTML anchors, see anchorId()
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}
     */
    public function elements( string $html, array $footnotes = [], int|string|null $postId = null ): array
    {
        $this->postId = $postId;
        // block names can contain the "core/" namespace like "<!-- wp:core/paragraph -->", which is the same block for WordPress
        $html = $this->result( preg_replace( '/<!--(\s*\/?)wp:core\//', '<!--$1wp:', $html ), $html );
        $html = $this->noTeaser( $html );
        // the "more" tag can contain the text of the link like "<!--more Read on-->", see get_extended()
        $html = $this->result( preg_replace( '/<!--more(.*?)?-->|<!--noteaser-->/', '', $html ), $html );
        $html = str_replace( "\r\n", "\n", $html );
        // the parts of posts split into several pages are shown one after another
        $html = str_replace( '<!--nextpage-->', "\n\n", $html );
        $html = $this->footnoteRefs( $html, $footnotes );
        $this->anchors = [];
        $this->unknownShortcodes( $html );

        $elements = [];
        $fileIds = [];

        // Detect Gutenberg content and use block comments as type hints
        $this->classic = !preg_match( '/<!--\s*wp:/', $html );
        $results = $this->classic ? $this->htmlBlocks( $html ) : $this->gutenbergBlocks( $html );
        $this->classic = false;

        foreach( array_filter( $results ) as $result )
        {
            array_push( $elements, ...$result['elements'] );
            array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
        }

        // footnotes are shown at the end if the post has no footnotes block
        if( !$this->footnotesAdded && ( $result = $this->footnotesBlock() ) ) {
            array_push( $elements, ...$result['elements'] );
        }

        return ['elements' => $this->anchorLinks( $this->mergeConsecutiveText( $elements ) ), 'fileIds' => $fileIds];
    }


    /**
     * Warns about the shortcodes of plugins and themes, which are kept as text because only the ones of WordPress core are converted.
     *
     * Shortcodes are recognized by their attributes like "[contact-form-7 id="5"]" or by their closing tag like
     * "[/su_button]", so texts in brackets like "[sic]" aren't reported.
     */
    protected function unknownShortcodes( string $html ): void
    {
        if( !$this->warn || !str_contains( $html, '[' ) ) {
            return;
        }

        preg_match_all( '/(?<!\[)\[([a-z][\w-]*)\s[^\[\]]*=[^\[\]]*\]|\[\/([a-z][\w-]*)\]/i', $html, $matches );
        $names = array_diff( array_unique( array_filter( [...$matches[1], ...$matches[2]] ) ), Shortcode::TAGS );

        if( $names ) {
            ( $this->warn )( '  Shortcodes kept as text: ' . implode( ', ', $names ) );
        }
    }


    /**
     * Remembers the element ID of a block with an HTML anchor like "<h2 id="intro">" for links like "#intro".
     *
     * @param array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null $result Content of the block
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null Unchanged content
     */
    protected function anchor( string $html, ?array $result ): ?array
    {
        if( is_string( $id = $result['elements'][0]['id'] ?? null ) && preg_match( '/^<[a-z][a-z0-9]*\s[^>]*?\bid=["\']([^"\']+)["\']/i', ltrim( $html ), $m )
            && !isset( $this->anchors[$anchor = Html::decode( $m[1] )] ) )
        {
            // links from other posts to the anchor know the element ID in advance
            if( $this->postId !== null ) {
                $result['elements'][0]['id'] = $id = self::anchorId( $this->postId, $anchor );
            }

            $this->anchors[$anchor] = $id;
        }

        return $result;
    }


    /**
     * Returns the element ID of the block with the HTML anchor in the post.
     *
     * The ID has the format of Utils::uid() and is the same for each import, so links from other posts like
     * "/my-post/#intro" point to the element regardless of the order the posts are imported in.
     *
     * @param int|string $postId ID of the post containing the block
     * @param string $anchor Decoded HTML anchor of the block like "intro"
     * @return string Element ID
     */
    public static function anchorId( int|string $postId, string $anchor ): string
    {
        $base64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $bytes = md5( $postId . '#' . $anchor, true );
        $id = $base64[ord( $bytes[0] ) % 52];

        for( $i = 1; $i < 6; $i++ ) {
            $id .= $base64[ord( $bytes[$i] ) & 63];
        }

        return $id;
    }


    /**
     * Replaces the links to the HTML anchors of the post by links to the elements, which have their ID as anchor.
     *
     * @param array<int, array<string, mixed>> $elements Content elements of the post
     * @return array<int, array<string, mixed>> Content elements with replaced links
     */
    protected function anchorLinks( array $elements ): array
    {
        if( !$this->anchors ) {
            return $elements;
        }

        foreach( $elements as $idx => $el )
        {
            if( is_string( $text = $el['data']['text'] ?? null ) && str_contains( $text, '#' ) )
            {
                $elements[$idx]['data']['text'] = $this->result( preg_replace_callback( '/(\]\(|\bhref=["\'])#([^)\s"\'<>]+)/', function( $m ) {
                    return $m[1] . '#' . ( $this->anchors[rawurldecode( Html::decode( $m[2] ) )] ?? $m[2] );
                }, $text ), $text );
            }
        }

        return $elements;
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

            if( $entry['type'] === 'footnotes' )
            {
                $results[] = $this->footnotesBlock();
                continue;
            }

            // reusable blocks are replaced by their content before, see Reader::reusable()
            if( $entry['type'] === 'block' )
            {
                if( $this->warn ) {
                    ( $this->warn )( '  Reusable block ' . json_encode( $entry['attrs']['ref'] ?? null ) . ' not found, unpublished or password protected, skipped' );
                }
                continue;
            }

            // other self-closing blocks are dynamic blocks rendered by WordPress like the latest posts, the query loop
            // and comments blocks show the posts and comments by their dynamic inner blocks, so their content isn't used
            if( $entry['type'] !== null && ( $entry['html'] === '' || in_array( $entry['type'], ['query', 'comments', 'terms-query'], true ) ) )
            {
                if( $this->warn ) {
                    ( $this->warn )( '  Dynamic block ' . $entry['type'] . ' skipped' );
                }
                continue;
            }

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

                $results[] = $this->imageLink( $this->imageText( $fileId, implode( "\n", $textParts ), $align === 'left' ? 'start' : 'end' ), $block, $entry['attrs'] );
                continue;
            }

            // WordPress expands shortcodes and embeds URLs in the whole content, not only in classic content and shortcode blocks
            if( in_array( $entry['type'], [null, 'shortcode', 'paragraph', 'html'], true ) && ( preg_match( Shortcode::pattern(), $block ) || preg_match( self::AUTOEMBED, $block ) ) )
            {
                array_push( $results, ...$this->htmlBlocks( $block ) );
                continue;
            }

            // Parse wp:columns, cover and media-text blocks before comments are stripped to keep the types of the inner blocks
            $result = match( $entry['type'] ) {
                'columns' => $this->parseColumnsBlock( $entry['html'] ),
                'cover', 'media-text' => $this->parseMediaTextBlock( $entry['html'] ),
                'image' => $this->imageLink( $this->parseBlock( $block, $entry['type'] ), $block, $entry['attrs'] ),
                'file' => $this->parseFileBlock( $block, $entry['attrs'] ) ?? $this->text( $block ),
                'social-links' => $this->parseSocialLinksBlock( $entry['html'] ),
                'playlist' => $this->parsePlaylistBlock( $entry['html'] ) ?? $this->parseBlock( $block, $entry['type'] ),
                'details' => $this->parseDetailsBlock( $entry['html'] ) ?? $this->parseBlock( $block, $entry['type'] ),
                'accordion' => $this->parseAccordionBlock( $entry['html'] ) ?? $this->parseBlock( $block, $entry['type'] ),
                'text-columns' => $this->parseTextColumnsBlock( $block ) ?? $this->parseBlock( $block, $entry['type'] ),
                'math' => $this->parseMathBlock( $block ) ?? $this->parseBlock( $block, $entry['type'] ),
                // SyntaxHighlighter blocks have the language only in the block attributes
                'syntaxhighlighter/code' => $this->parseCodeBlock( $block, is_string( $l = $entry['attrs']['language'] ?? null ) ? $l : null ) ?? $this->text( $block ),
                default => $this->parseBlock( $block, $entry['type'] ),
            };

            $results[] = $this->anchor( $block, $result );
        }

        return $results;
    }


    /**
     * Converts classic content into content, the shortcodes of WordPress core are converted separately.
     *
     * @return array<int, array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null>
     */
    protected function htmlBlocks( string $html ): array
    {
        $results = [];
        $offset = 0;
        $text = '';

        preg_match_all( Shortcode::pattern(), $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

        foreach( $matches as $m )
        {
            $before = substr( $html, $offset, $m[0][1] - $offset );
            $offset = $m[0][1] + strlen( $m[0][0] );

            // escaped shortcodes like "[[gallery]]" are shown as text without the outer brackets
            if( $m[1][0] === '[' && $m[6][0] === ']' )
            {
                $text .= $before . substr( $m[0][0], 1, -1 );
                continue;
            }

            array_push( $results, ...$this->classicBlocks( $text . $before ) );
            $results[] = $this->shortcode( $m[2][0], Shortcode::attributes( $m[3][0] ), $m[5][0] ?? '' );
            $text = '';
        }

        return [...$results, ...$this->classicBlocks( $text . substr( $html, $offset ) )];
    }


    /**
     * Converts the HTML blocks of classic content without shortcodes into content.
     *
     * @return array<int, array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null>
     */
    protected function classicBlocks( string $html ): array
    {
        $results = [];
        $blocks = array_values( array_filter( array_map( 'trim', $this->splitIntoBlocks( $html ) ) ) );
        $count = count( $blocks );

        for( $i = 0; $i < $count; $i++ )
        {
            $block = $blocks[$i];

            // images floated by the "alignleft" or "alignright" class are shown next to the following texts like aligned image blocks
            if( preg_match( '/^(?:<a\b[^>]*>\s*)?<img\b[^>]*\bclass=["\'][^"\']*\balign(left|right)\b[^>]*>(?:\s*<\/a>)?$/is', $block, $align ) )
            {
                $textParts = [];

                while( $i + 1 < $count && !preg_match( '/^<(?:pre|h[1-6]|div\s+class="(?:tip|note|caution)")|<img\b/i', $blocks[$i + 1] )
                    && !preg_match( self::AUTOEMBED, $blocks[$i + 1] ) )
                {
                    $textParts[] = $this->autop( $blocks[++$i] );
                }

                if( $textParts ) {
                    $results[] = $this->imageText( $this->image( $block ), implode( "\n\n", $textParts ), strtolower( $align[1] ) === 'left' ? 'start' : 'end' );
                    continue;
                }
            }

            // details convert their content like classic content themselves
            if( preg_match( '/^<details\b/i', $block ) )
            {
                $results[] = $this->anchor( $block, $this->parseDetailsBlock( $block ) ?? $this->parseBlock( $this->autop( $block ) ) );
                continue;
            }

            if( preg_match( '/^<(?:pre|h[1-6])\b/i', $block ) )
            {
                $results[] = $this->anchor( $block, $this->parseBlock( $block ) );
                continue;
            }

            // URLs on their own line or in their own paragraph are embedded by WordPress, the URL is captured by the pattern
            foreach( preg_split( self::AUTOEMBED, $block, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [$block] as $idx => $part )
            {
                if( $idx % 2 ) {
                    $results[] = $this->embed( $part );
                } elseif( !empty( $part = trim( $part ) ) ) {
                    // notices split their text into paragraphs at blank lines themselves
                    $notice = preg_match( '/^<div class="(?:tip|note|caution)">/i', $part );
                    $results[] = $this->anchor( $part, $this->parseBlock( $notice ? $part : $this->autop( $part ) ) );
                }
            }
        }

        return $results;
    }


    /**
     * Removes the teaser before the "more" tag which isn't shown on the page of the post like get_the_content().
     *
     * WordPress hides the teaser if the content contains "<!--noteaser-->", e.g. from the "Hide the excerpt on the
     * full content page" setting of the more block, and the "more" tag is on the first page of paginated posts.
     */
    protected function noTeaser( string $html ): string
    {
        if( !str_contains( $html, '<!--noteaser-->' ) ) {
            return $html;
        }

        $first = explode( '<!--nextpage-->', $html, 2 )[0];

        if( !preg_match( '/<!--more(.*?)?-->/', $first, $m, PREG_OFFSET_CAPTURE ) ) {
            return $html;
        }

        return substr( $html, $m[0][1] + strlen( $m[0][0] ) );
    }


    /**
     * Adds line breaks for the single newlines of classic content like wpautop(), except next to block tags.
     *
     * The URLs on their own line are embedded before like WordPress does, so the line breaks don't hide them.
     */
    protected function autop( string $html ): string
    {
        if( !$this->classic || !str_contains( $html, "\n" ) ) {
            return $html;
        }

        // newlines in tags only separate attributes
        $html = $this->result( preg_replace_callback( '/<[^>]*>/s', fn( $m ) => str_replace( "\n", ' ', $m[0] ), $html ), $html );
        $html = $this->result( preg_replace( '/(?:<br\s*\/?>)?[ \t]*\n\s*/i', '<br>', trim( $html ) ), $html );

        return $this->result( preg_replace( [
            '#(</?(?:' . self::AUTOP_BLOCKS . ')\b[^>]*>)<br>#i',
            '#<br>(</?(?:' . self::AUTOP_BLOCKS . ')\b[^>]*>)#i',
        ], '$1', $html ), $html );
    }


    /**
     * Converts a text block into a text element with Markdown or an HTML element.
     *
     * If the HTML can be fully converted to Markdown, the "text" element is used,
     * otherwise the "html" element.
     *
     * @return ContentElements|null
     */
    #[\Override]
    public function text( string $block ): ?array
    {
        $block = trim( $block );

        if( empty( $block ) ) {
            return null;
        }

        $block = $this->citations( $this->reversedLists( $block ) );
        $markdown = Markdown::fromHtml( $block );

        if( !empty( $markdown ) && !$this->hasUnconvertibleHtml( $block ) ) {
            return Elements::content( Elements::create( 'text', ['text' => $markdown] ) );
        }

        // forms, scripts and other tags are removed when sanitizing the HTML
        if( $this->warn && preg_match_all( '/<(' . self::REMOVED_TAGS . ')\b/i', $block, $m ) ) {
            ( $this->warn )( '  HTML removed: ' . implode( ', ', array_unique( array_map( 'strtolower', $m[1] ) ) ) );
        }

        // HTML without content after sanitizing like "<!--nextpage-->" isn't added as empty element
        return parent::text( $block );
    }


    /**
     * Adds the citation of quotes and pullquotes as last paragraph, because the <cite> tag can't be converted to Markdown.
     */
    protected function citations( string $html ): string
    {
        if( !str_contains( $html, '<cite' ) ) {
            return $html;
        }

        $html = $this->result( preg_replace_callback( '/<cite\b[^>]*>(.*?)<\/cite>(\s*<\/blockquote>)/is', function( $m ) {
            return ( trim( strip_tags( $m[1] ) ) !== '' ? '<p>— ' . trim( $m[1] ) . '</p>' : '' ) . $m[2];
        }, $html ), $html );

        // other citations like the titles of works are shown in italics like by the browsers
        return $this->result( preg_replace( '/<cite\b[^>]*>(.*?)<\/cite>/is', '<em>$1</em>', $html ), $html );
    }


    /**
     * Numbers the items of reversed lists, because the "reversed" attribute is removed when sanitizing the HTML.
     */
    protected function reversedLists( string $html ): string
    {
        if( !preg_match( '/<ol\b[^>]*\sreversed\b/i', $html ) ) {
            return $html;
        }

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors( true );
        $loaded = $doc->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
        libxml_clear_errors();
        libxml_use_internal_errors( $prev );

        if( !$loaded || !( $root = $doc->getElementsByTagName( 'div' )->item( 0 ) ) ) {
            return $html;
        }

        foreach( iterator_to_array( $doc->getElementsByTagName( 'ol' ) ) as $list )
        {
            if( !$list->hasAttribute( 'reversed' ) ) {
                continue;
            }

            $items = array_filter( iterator_to_array( $list->childNodes ), fn( $node ) => $node instanceof \DOMElement && strtolower( $node->tagName ) === 'li' );
            $number = $list->hasAttribute( 'start' ) ? (int) $list->getAttribute( 'start' ) : count( $items );

            foreach( $items as $item ) {
                $item->setAttribute( 'value', (string) $number-- );
            }

            $list->removeAttribute( 'reversed' );
        }

        $result = '';
        foreach( $root->childNodes as $node ) {
            $result .= $doc->saveHTML( $node );
        }

        return $result;
    }


    /**
     * Cleans a code block by removing HTML tags and decoding entities.
     */
    protected function cleanCodeBlock( string $code ): string
    {
        $code = $this->result( preg_replace( '/<\/?code>/i', '', $code ), $code );
        $code = $this->result( preg_replace( '/<br\s*\/?>/i', "\n", $code ), $code );
        $code = strip_tags( $code );
        $code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

        // the indentation of the first line is kept, only empty lines at the start and end are removed
        return rtrim( $this->result( preg_replace( '/^(?:[ \t]*\n)+/', '', $code ), $code ) );
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
        // superscript and subscript like "m<sup>2</sup>" aren't available in Markdown, only the footnote numbers like "<sup>[1]</sup>" are kept
        $convertible = 'p|br|strong|b|em|i|a|code|ul|ol|li|blockquote|span|del|s|mark|abbr|hr';
        $stripped = preg_replace( ['/<sup>\[\d+\]<\/sup>/', '/<\/?(' . $convertible . ')(\s[^>]*)?\/?>/i'], '', $html );

        // Markdown can't use the numbers of list items, e.g. of reversed lists
        return (bool) preg_match( '/<[a-z][a-z0-9]*[\s>\/]/i', $stripped ?? '' ) || (bool) preg_match( '/<li\b[^>]*\svalue=/i', $html )
            // Markdown lists can't use letters and roman numbers
            || (bool) preg_match( '/<ol\b[^>]*(?:\stype=["\']?[aAiI]\b|\sstyle=["\'][^"\']*list-style(?:-type)?:\s*(?:lower|upper)-(?:alpha|latin|roman|greek))/i', $html );
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
        $html = $this->result( preg_replace( '/<(figure|div|figcaption)[^>]*>|<\/(figure|div|figcaption)>/i', '', $html ), $html );
        // headings like the ones of covers are bold paragraphs because image-text elements have no title
        $html = $this->result( preg_replace( '/<h[1-6]\b[^>]*>(.*?)<\/h[1-6]>/is', '<p><strong>$1</strong></p>', $html ), $html );
        // like the other texts, citations are marked and reversed lists count down
        $html = $this->citations( $this->reversedLists( $html ) );

        // numbered list items can't be converted to Markdown, so the image and the HTML text are separate elements
        if( preg_match( '/<li\b[^>]*\svalue=/i', $html ) ) {
            return Elements::merge( $fileId ? Elements::fileContent( 'image', $fileId ) : Elements::content(), $this->text( $html ) );
        }

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
            return Elements::fileContent( 'image', $fileId );
        }

        return ( !empty( $text ) ? $this->text( $text ) : null ) ?? Elements::content();
    }


    /**
     * Replaces the footnote references of the post content by their numbers in brackets like "[1]".
     *
     * WordPress numbers the footnotes in the order of their references, the anchors of the references
     * aren't available in Pagible texts. The footnotes are remembered for footnotesBlock().
     *
     * @param array<string, string> $footnotes HTML texts of the footnotes by their ID
     */
    protected function footnoteRefs( string $html, array $footnotes ): string
    {
        $numbers = [];
        $this->footnotesAdded = false;

        if( str_contains( $html, 'data-fn' ) )
        {
            $html = $this->result( preg_replace_callback( '/<sup\b[^>]*\bdata-fn=["\']([^"\']+)["\'][^>]*>.*?<\/sup>/is', function( $m ) use ( &$numbers ) {
                return '<sup>[' . ( $numbers[$m[1]] ??= count( $numbers ) + 1 ) . ']</sup>';
            }, $html ), $html );
        }

        // footnotes without reference are shown after the referenced ones
        $this->footnotes = array_filter( array_replace( array_fill_keys( array_map( 'strval', array_keys( $numbers ) ), '' ), $footnotes ), fn( $text ) => trim( $text ) !== '' );

        return $html;
    }


    /**
     * Converts the footnotes of the post into a text element with a numbered list.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function footnotesBlock(): ?array
    {
        if( $this->footnotesAdded || !$this->footnotes ) {
            return null;
        }

        $this->footnotesAdded = true;
        return $this->text( '<ol>' . implode( '', array_map( fn( $text ) => '<li>' . $text . '</li>', $this->footnotes ) ) . '</ol>' );
    }


    /**
     * Merges the elements and file IDs of several results into one result.
     *
     * @param array<int, array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null> $results
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function merge( array $results ): ?array
    {
        $elements = $fileIds = [];

        foreach( array_filter( $results ) as $result )
        {
            array_push( $elements, ...$result['elements'] );
            array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
        }

        return $elements ? ['elements' => $elements, 'fileIds' => $fileIds] : null;
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

            // elements with HTML anchors are kept separately, so links point to the start of their text
            if( $prev && $prev['type'] === $el['type']
                && in_array( $el['type'], ['text', 'html'] )
                && isset( $prev['data']['text'] ) && isset( $el['data']['text'] )
                && !in_array( $el['id'] ?? null, $this->anchors, true ) )
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
     * Parses an accordion block into a questions element with the titles of the headings and the texts of the panels.
     *
     * Accordions whose panels contain more than text like images are converted like other blocks.
     *
     * @return ContentElements|null
     */
    protected function parseAccordionBlock( string $block ): ?array
    {
        $pattern = '/<!--\s*wp:accordion-item(?:\s+' . self::ATTRS . ')?\s*-->(.*?)<!--\s*\/wp:accordion-item\s*-->/is';

        if( !preg_match_all( $pattern, $block, $matches ) ) {
            return null;
        }

        $items = $results = [];

        foreach( $matches[1] as $item )
        {
            $title = preg_match( '/<span\b[^>]*\bwp-block-accordion-heading__toggle-title\b[^>]*>(.*?)<\/span>/is', $item, $m )
                ? trim( Html::decode( strip_tags( $m[1] ) ) ) : '';
            $panel = preg_match( '/<!--\s*wp:accordion-panel(?:\s+' . self::ATTRS . ')?\s*-->(.*?)<!--\s*\/wp:accordion-panel\s*-->/is', $item, $m )
                ? $this->result( preg_replace( '/^\s*<div\b[^>]*>(.*)<\/div>\s*$/is', '$1', $m[1] ), $m[1] ) : '';
            $text = $this->text( $this->stripWpComments( $panel ) );

            // questions need a title and an answer which is a text
            if( $items !== null && $title !== '' && count( $text['elements'] ?? [] ) === 1 && ( $text['elements'][0]['type'] ?? null ) === 'text' ) {
                $items[] = ['title' => $title, 'text' => $text['elements'][0]['data']['text'] ?? ''];
            } else {
                $items = null;
            }

            // other items are converted like details blocks with the bold title followed by the inner blocks of the panel
            $title = $title !== '' ? $this->text( '<p><strong>' . htmlspecialchars( $title, ENT_QUOTES ) . '</strong></p>' ) : null;
            $results[] = $this->merge( [$title, ...( preg_match( '/<!--\s*wp:/', $panel ) ? $this->gutenbergBlocks( $panel ) : [$text] )] );
        }

        return $items !== null ? Elements::content( Elements::create( 'questions', ['title' => '', 'items' => $items] ) ) : $this->merge( $results );
    }


    /**
     * Parses a deprecated text columns block, whose columns contain HTML without inner blocks, into text.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseTextColumnsBlock( string $block ): ?array
    {
        if( !preg_match_all( '/<div\b[^>]*\bclass="[^"]*\bwp-block-column\b[^"]*"[^>]*>(.*?)<\/div>/is', $block, $matches ) ) {
            return null;
        }

        return $this->merge( array_map( fn( $html ) => $this->text( $html ), $matches[1] ) );
    }


    /**
     * Parses a math block into a code element with the LaTeX source of the formula, which can't be shown as formula.
     *
     * @return ContentElements|null
     */
    protected function parseMathBlock( string $block ): ?array
    {
        if( !preg_match( '/<annotation\b[^>]*\bencoding=["\']application\/x-tex["\'][^>]*>(.*?)<\/annotation>/is', $block, $m )
            || ( $latex = trim( Html::decode( $m[1] ) ) ) === '' )
        {
            return null;
        }

        if( $this->warn ) {
            ( $this->warn )( '  Math formula kept as LaTeX code: ' . $latex );
        }

        return Elements::content( Elements::create( 'code', ['language' => 'latex', 'text' => $latex] ) );
    }


    /**
     * Parses an audio block into an audio element followed by the caption as text.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseAudioBlock( string $block ): ?array
    {
        if( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $block, $m ) ) {
            return $this->merge( [$this->parseAudioBlock( str_replace( $m[0], '', $block ) ), $this->text( '<p>' . trim( $m[1] ) . '</p>' )] );
        }

        if( !preg_match( '/src=["\']([^"\']+)["\']/', $block, $m ) ) {
            return null;
        }

        $fileId = ( $this->file )( Html::decode( $m[1] ), '' );

        if( !$fileId ) {
            return null;
        }

        return Elements::fileContent( 'audio', $fileId );
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
            if( in_array( $wpType, ['spacer', 'columns', 'column', 'group', 'more'] ) ) {
                return null;
            }

            // separators are horizontal lines like in classic content
            if( $wpType === 'separator' ) {
                return $this->text( '<hr>' );
            }

            // Use Gutenberg type hints to map to Pagible schema types
            if( $wpType === 'heading' ) {
                return $this->parseHeadingBlock( $block ) ?? $this->text( $block );
            }

            if( in_array( $wpType, ['code', 'syntaxhighlighter/code'] ) ) {
                return $this->parseCodeBlock( $block ) ?? $this->text( $block );
            }

            // preformatted texts with formatting like bold text are kept as HTML, plain ones keep their spacing as code
            if( $wpType === 'preformatted' ) {
                return preg_match( '/^<pre\b[^>]*>.*<(?!br\b)[a-z][^>]*>.*<\/pre>$/is', $block ) ? parent::text( $block ) : $this->parseCodeBlock( $block ) ?? $this->text( $block );
            }

            if( $wpType === 'details' ) {
                return $this->parseDetailsBlock( $block ) ?? $this->text( $block );
            }

            if( $wpType === 'image' ) {
                return $this->parseStandaloneImageBlock( $block ) ?? $this->parseImageTextBlock( $block );
            }

            if( $wpType === 'gallery' ) {
                return $this->parseGalleryBlock( $block ) ?? $this->parseStandaloneImageBlock( $block );
            }

            if( in_array( $wpType, ['media-text', 'cover'] ) ) {
                return $this->parseMediaTextBlock( $block );
            }

            if( in_array( $wpType, ['buttons', 'button'] ) ) {
                return $this->parseButtonsBlock( $block );
            }

            if( $wpType === 'file' ) {
                return $this->parseFileBlock( $block ) ?? $this->text( $block );
            }

            if( $wpType === 'table' ) {
                return $this->parseTableBlock( $block ) ?? $this->text( $block );
            }

            if( in_array( $wpType, ['video', 'embed'] ) || str_starts_with( $wpType, 'core-embed/' ) ) {
                return $this->parseEmbedBlock( $block );
            }

            if( $wpType === 'audio' ) {
                return $this->parseAudioBlock( $block );
            }

            // poems and lyrics keep their line breaks but aren't code like preformatted texts
            if( $wpType === 'verse' ) {
                return $this->text( $this->verse( $block ) );
            }

            // the figure of pullquotes only wraps the quote
            if( $wpType === 'pullquote' ) {
                $block = $this->result( preg_replace( '/^<figure\b[^>]*>(.*)<\/figure>$/is', '$1', $block ), $block );
            }

            if( in_array( $wpType, ['paragraph', 'list', 'quote', 'pullquote', 'freeform'] ) ) {
                if( preg_match( '/<img[^>]+>/i', $block ) ) {
                    return $this->inlineImages( $block ) ?? $this->parseImageTextBlock( $block );
                }
                return $this->text( $block );
            }
        }

        // iframes like the ones in Custom HTML blocks are removed when sanitizing the HTML
        if( preg_match( '/<iframe\b/i', $block ) ) {
            return $this->iframes( $block );
        }

        // Fall through to HTML-based detection for unknown/null types
        $result = $this->parseHeadingBlock( $block ) ?? $this->parseCodeBlock( $block )
            ?? $this->parseStandaloneImageBlock( $block ) ?? $this->parseNoticeBlock( $block )
            ?? ( preg_match( '/^<table\b[^>]*>.*<\/table>$/is', $block ) ? $this->parseTableBlock( $block ) : null );

        if( $result ) {
            return $result;
        }

        if( preg_match( '/<img[^>]+>/i', $block ) ) {
            return $this->inlineImages( $block ) ?? $this->parseImageTextBlock( $block );
        }

        return $this->text( $block );
    }


    /**
     * Keeps a text with small images shown within the text like icons as HTML.
     *
     * The inline images of the block editor and small images of classic content without alignment and size
     * class are shown within the text by WordPress. Pagible elements can't contain images, so the images are
     * imported as files and the HTML refers to the URLs of the stored files. Images which can't be imported
     * keep their URL.
     *
     * @return ContentElements|null HTML element or NULL if the images aren't inline images or there's no text
     */
    protected function inlineImages( string $block ): ?array
    {
        preg_match_all( '/<img\b[^>]*>/i', $block, $matches );

        foreach( $matches[0] as $tag )
        {
            $width = preg_match( '/\swidth=["\']?(\d+)|\sstyle=["\'][^"\']*(?<![\w-])width:\s*(\d+(?:\.\d+)?)px/i', $tag, $m ) ? (float) ( $m[2] ?? '' ?: $m[1] ) : 0;

            if( preg_match( '/\sclass=["\'][^"\']*\b(?:align[a-z]+|size-[a-z0-9_-]+)\b/i', $tag ) || $width <= 0 || $width > self::INLINE_WIDTH ) {
                return null;
            }
        }

        if( !$matches[0] || trim( html_entity_decode( strip_tags( $block ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), " \t\n\r\0\x0B\u{a0}" ) === '' ) {
            return null;
        }

        $urls = $fileIds = [];
        $html = $this->result( preg_replace_callback( '/<img\b[^>]*>/i', function( $m ) use ( &$urls, &$fileIds ) {
            // the other sizes of the image aren't imported, the stored file is shown in all sizes
            $tag = $this->result( preg_replace( '/\s(?:srcset|sizes)=(?:"[^"]*"|\'[^\']*\')/i', '', $m[0] ), $m[0] );
            $fileId = $this->src ? ( $this->image )( $tag ) : null;
            $url = $fileId !== null ? ( $this->src )( $fileId ) : null;

            if( $url === null ) {
                $urls[] = Html::decodedAttribute( $tag, 'src' );
                return $tag;
            }

            $fileIds[] = $fileId;
            $urls[] = $url;

            return $this->result( preg_replace( '/\ssrc=(["\']).*?\1/i', ' src="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '"', $tag, 1 ), $tag );
        }, $block ), $block );

        if( $this->warn && $urls ) {
            ( $this->warn )( '  Inline images kept as HTML: ' . implode( ', ', array_unique( $urls ) ) );
        }

        // the "reversed" attribute is removed when sanitizing the HTML, so the items are numbered before
        return ( $content = parent::text( $this->reversedLists( $html ) ) ) ? Elements::merge( $content, ['elements' => [], 'fileIds' => $fileIds] ) : null;
    }


    /**
     * Converts the iframes of the HTML into HTML elements, the other HTML is converted separately.
     *
     * The iframes of YouTube and Vimeo players are kept as iframes because their videos can't be shown by video elements.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function iframes( string $html ): ?array
    {
        $results = [];
        $parts = preg_split( '/(<iframe\b[^>]*>(?:.*?<\/iframe>)?)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [$html];

        foreach( $parts as $idx => $part )
        {
            if( $idx % 2 )
            {
                $results[] = $this->iframe( preg_match( '/^<iframe\b[^>]*>/i', $part, $m ) ? $m[0] : $part );
            }
            elseif( trim( strip_tags( $part, '<img><video><audio><table>' ) ) !== '' || preg_match( '/<(?:' . self::REMOVED_TAGS . ')\b/i', $part ) )
            {
                $results[] = $this->parseBlock( trim( $part ) );
            }
        }

        return $this->merge( $results );
    }


    /**
     * Warns about iframes removed because their URL isn't a web URL.
     *
     * @param string $tag Opening iframe tag
     */
    #[\Override]
    protected function iframeRemoved( string $tag ): void
    {
        if( $this->warn ) {
            ( $this->warn )( '  Iframe removed: ' . Html::decodedAttribute( $tag, 'src' ) );
        }
    }


    /**
     * Converts the <pre> tag of a verse block into a paragraph with line breaks.
     */
    protected function verse( string $block ): string
    {
        if( !preg_match( '/^<pre\b[^>]*>(.*)<\/pre>$/is', $block, $m ) ) {
            return $block;
        }

        $lines = preg_split( '/<br\s*\/?>|\n/i', trim( $m[1] ) ) ?: [];
        return '<p>' . implode( '<br>', array_map( 'trim', $lines ) ) . '</p>';
    }


    /**
     * Parses a heading into a heading element and warns about the links in the heading, which are removed.
     *
     * @return ContentElements|null
     */
    #[\Override]
    protected function parseHeadingBlock( string $block ): ?array
    {
        $result = parent::parseHeadingBlock( $block );

        if( $result && $this->warn && preg_match_all( '/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $block, $m ) ) {
            ( $this->warn )( '  Links removed from heading "' . ( $result['elements'][0]['data']['title'] ?? '' ) . '": ' . implode( ', ', array_map( [Html::class, 'decode'], $m[1] ) ) );
        }

        return $result;
    }


    /**
     * Parses a buttons block into a text with the links of the buttons, each in its own paragraph.
     *
     * Like WordPress, buttons without content except comments aren't shown.
     *
     * @return ContentElements|null
     */
    protected function parseButtonsBlock( string $block ): ?array
    {
        if( !preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $block, $m ) ) {
            return null;
        }

        $links = array_filter( $m[0], fn( $link, $idx ) => preg_replace( '/<!--.*?-->/s', '', $m[1][$idx] ) !== '', ARRAY_FILTER_USE_BOTH );

        return $links ? $this->text( implode( '', array_map( fn( $link ) => '<p>' . strip_tags( $link, '<a><strong><b><em><i>' ) . '</p>', $links ) ) ) : ['elements' => [], 'fileIds' => []];
    }


    /**
     * Parses a <pre> tag into a code element.
     *
     * The language is taken from the "language" attribute of SyntaxHighlighter blocks, the "language-*" class
     * of Prism and highlight.js, the "brush" class of SyntaxHighlighter or the "lang" attribute of WP-Syntax.
     *
     * @param string|null $language Language from the attributes of the block, NULL to use the HTML
     * @return ContentElements|null
     */
    #[\Override]
    protected function parseCodeBlock( string $block, ?string $language = null ): ?array
    {
        if( !preg_match( '/^<pre[^>]*>(.*?)<\/pre>$/is', $block, $m ) ) {
            return null;
        }

        if( $language === null || ( $language = $this->codeLanguage( $language ) ) === '' )
        {
            $language = '';

            foreach( [
                '/\bclass=["\'][^"\']*\blanguage-([\w+#.-]+)/i',
                '/\bclass=["\'][^"\']*\bbrush:\s*([\w+#.-]+)/i',
                '/<pre\b[^>]*(?<![\w:-])lang=["\']?([\w+#.-]+)/i',
            ] as $pattern )
            {
                if( preg_match( $pattern, $block, $lang ) && ( $language = $this->codeLanguage( $lang[1] ) ) !== '' ) {
                    break;
                }
            }
        }

        return Elements::content( Elements::create( 'code', [
            'language' => $language,
            'text' => $this->cleanCodeBlock( $m[1] ),
        ] ) );
    }


    /**
     * Returns the language of the code in lower case.
     *
     * Languages of texts with a region like "en-US" in the "lang" attribute aren't languages of code.
     *
     * @return string Language of the code or an empty string if it isn't valid
     */
    protected function codeLanguage( string $language ): string
    {
        $language = strtolower( trim( $language ) );
        return preg_match( '/^[\w+#.-]+$/', $language ) && !preg_match( '/^[a-z]{2,3}[-_][a-z]{2,4}$/', $language ) ? $language : '';
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
            $colContent = $this->result( preg_replace( '/^<div[^>]*>(.*)<\/div>$/is', '$1', $trimmed = trim( $colContent ) ), $trimmed );

            // the inner blocks are converted like the top level blocks, so dynamic blocks are reported too
            foreach( array_filter( $this->gutenbergBlocks( $colContent ) ) as $result )
            {
                array_push( $elements, ...$result['elements'] );
                array_push( $fileIds, ...( $result['fileIds'] ?? [] ) );
            }
        }

        if( !empty( $elements ) ) {
            return ['elements' => $elements, 'fileIds' => $fileIds];
        }

        return null;
    }


    /**
     * Parses a social links block into a list of links, the URLs are only in the attributes of the inner blocks.
     *
     * Links without label show the name of the service like in WordPress.
     *
     * @return ContentElements|null
     */
    protected function parseSocialLinksBlock( string $block ): ?array
    {
        preg_match_all( '/<!--\s*wp:(?:core\/)?social-link\s+(' . self::ATTRS . ')\s*\/-->/is', $block, $matches );
        $items = [];

        foreach( $matches[1] as $json )
        {
            $attrs = (array) json_decode( $json, true );

            if( !is_string( $url = $attrs['url'] ?? null ) || ( $url = trim( $url ) ) === '' ) {
                continue;
            }

            // like render_block_core_social_link(), emails get "mailto:" and URLs without scheme "https://"
            if( filter_var( $url, FILTER_VALIDATE_EMAIL ) ) {
                $url = 'mailto:' . $url;
            } elseif( !parse_url( $url, PHP_URL_SCHEME ) && !str_starts_with( $url, '//' ) && !str_starts_with( $url, '#' ) ) {
                $url = 'https://' . $url;
            }

            $label = is_string( $attrs['label'] ?? null ) && trim( $attrs['label'] ) !== '' ? trim( $attrs['label'] )
                : self::SOCIAL_NAMES[is_string( $attrs['service'] ?? null ) ? $attrs['service'] : ''] ?? 'Share Icon';
            $items[] = '<li><a href="' . htmlspecialchars( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '">'
                . htmlspecialchars( $label, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . '</a></li>';
        }

        return $items ? $this->text( '<ul>' . implode( '', $items ) . '</ul>' ) : null;
    }


    /**
     * Parses a details block into a text with the bold summary and the elements of the hidden content.
     *
     * The <details> and <summary> tags aren't available in Pagible texts, so the content is always visible.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseDetailsBlock( string $block ): ?array
    {
        if( !preg_match( '/^<details\b[^>]*>\s*<summary\b[^>]*>(.*?)<\/summary>(.*)<\/details>$/is', $block, $m ) ) {
            return null;
        }

        $summary = trim( strip_tags( $m[1], '<strong><b><em><i><a><code>' ) );
        $summary = $summary !== '' ? $this->text( '<p><strong>' . $summary . '</strong></p>' ) : null;

        // the content of details blocks are inner blocks, the ones of classic content HTML
        $content = preg_match( '/<!--\s*wp:/', $m[2] ) ? $this->gutenbergBlocks( $m[2] ) : $this->classicBlocks( $m[2] );

        return $this->merge( [$summary, ...$content] );
    }


    /**
     * Parses a playlist block into an audio element for each track with a source followed by the caption as text.
     *
     * The tracks are self-closing inner blocks which contain their data in the block attributes only.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parsePlaylistBlock( string $block ): ?array
    {
        $results = [];

        foreach( $this->splitGutenbergBlocks( $block ) as $entry )
        {
            // WordPress skips tracks without source URL
            if( $entry['type'] !== 'playlist-track' || !is_string( $src = $entry['attrs']['src'] ?? null ) || trim( $src ) === '' ) {
                continue;
            }

            $name = implode( ' - ', array_filter( [$entry['attrs']['title'] ?? '', $entry['attrs']['artist'] ?? ''], fn( $v ) => is_string( $v ) && trim( $v ) !== '' ) );

            if( $fileId = ( $this->file )( trim( $src ), $name ) ) {
                $results[] = Elements::fileContent( 'audio', $fileId );
            }
        }

        if( !$results ) {
            return null;
        }

        if( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $block, $m ) ) {
            $results[] = $this->text( '<p>' . trim( $m[1] ) . '</p>' );
        }

        return $this->merge( $results );
    }


    /**
     * Parses a video or embed block into a video element followed by the caption as text.
     *
     * Embeds of other services than YouTube and Vimeo become links to the embedded content.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseEmbedBlock( string $block ): ?array
    {
        $caption = null;

        if( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $block, $m ) )
        {
            $caption = $this->text( '<p>' . trim( $m[1] ) . '</p>' );
            $block = str_replace( $m[0], '', $block );
        }

        // the URL of embeds is the text content of the wrapper, the URL of videos the source of the video tag
        $result = $this->parseVideoBlock( $block ) ?? ( preg_match( '/https?:\/\/[^\s<>"]+/i', strip_tags( $block ), $url )
            ? $this->embed( $url[0] ) : $this->text( $block ) );

        return $this->merge( [$result, $caption] );
    }


    /**
     * Parses a gallery block into a slideshow element with multiple images.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseGalleryBlock( string $block ): ?array
    {
        // the caption of the gallery becomes the title, the captions of the single images aren't available
        $caption = '';
        if( preg_match( '/<figcaption\b[^>]*\bclass=["\'][^"\']*\bblocks-gallery-caption\b[^>]*>(.*?)<\/figcaption>/is', $block, $m ) )
        {
            $caption = trim( $m[1] );
            $block = str_replace( $m[0], '', $block );
        }

        if( !preg_match_all( '/<img[^>]+>/i', $block, $matches ) ) {
            return null;
        }

        $fileIds = array_values( array_filter( array_map( fn( $tag ) => $this->image( $tag ), $matches[0] ) ) );

        // galleries with one image are shown as image with the caption of the gallery
        if( count( $fileIds ) < 2 ) {
            return $fileIds ? $this->imageText( $fileIds[0], $caption ) : null;
        }

        return [
            'elements' => [Elements::create( 'slideshow', [
                'title' => trim( Html::decode( strip_tags( $caption ) ) ),
                'files' => array_map( fn( $id ) => Elements::file( $id ), $fileIds ),
            ] )],
            'fileIds' => $fileIds,
        ];
    }


    /**
     * Parses a file block into a file element, the link text is used as name of the file.
     *
     * @param array<mixed> $attrs Attributes of the file block with the URL of the file in "href"
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseFileBlock( string $block, array $attrs = [] ): ?array
    {
        $url = is_string( $attrs['href'] ?? null ) ? $attrs['href']
            : ( preg_match( '/<a\b[^>]*\bhref=["\']([^"\']+)["\']/i', $block, $m ) ? Html::decode( $m[1] ) : '' );

        if( trim( $url ) === '' ) {
            return null;
        }

        // the first link shows the file name unless the block only has the download button
        $name = preg_match( '/<a\b([^>]*)>(.*?)<\/a>/is', $block, $m ) && !str_contains( $m[1], 'wp-block-file__button' )
            ? trim( Html::decode( strip_tags( $m[2] ) ) ) : '';

        return ( $fileId = ( $this->file )( trim( $url ), $name ) ) ? Elements::fileContent( 'file', $fileId ) : null;
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
            return Elements::content();
        }

        return $this->imageText( $this->image( $imgMatch[0] ), str_replace( $imgMatch[0], '', $html ) );
    }


    /**
     * Parses a cover or media-text block into an image-text element or the video and the inner blocks.
     *
     * Covers can have a background image, video, color or gradient. The image of media-text blocks is on the
     * right side if the block has the "has-media-on-the-right" class. Inner blocks keep their block comments.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function parseMediaTextBlock( string $block ): ?array
    {
        $block = trim( $block );
        $inner = '';

        // the inner blocks are in the content container, the media of media-text blocks can follow it
        if( preg_match( '/<div\b[^>]*\bclass=["\'][^"\']*\bwp-block-(?:cover__inner-container|media-text__content)\b[^>]*>(.*)<\/div>\s*'
            . '(?:<figure\b(?:(?!<\/figure>).)*<\/figure>\s*)?<\/div>$/is', $block, $m ) )
        {
            $inner = trim( $m[1] );
            $block = str_replace( $m[1], '', $block );
        }

        $position = null;
        if( preg_match( '/\bwp-block-media-text\b/', $block ) ) {
            $position = preg_match( '/\bhas-media-on-the-right\b/', $block ) ? 'end' : 'start';
        }

        // covers before WordPress 5.7 have their image as CSS background
        if( !preg_match( '/(?:<a[^>]*>\s*)?<img[^>]+>(?:\s*<\/a>)?/i', $block, $img )
            && preg_match( '/background-image:\s*url\(\s*(?:&quot;|["\'])?([^"\')&]+)/i', $block, $bg ) )
        {
            $img = ['<img src="' . $bg[1] . '">'];
        }

        if( !empty( $img ) ) {
            return $this->imageText( $this->image( $img[0] ), $this->stripWpComments( $inner ), $position );
        }

        $content = preg_match( '/<!--\s*wp:/', $inner ) ? $this->gutenbergBlocks( $inner ) : $this->classicBlocks( $inner );
        return $this->merge( [$this->parseVideoBlock( $block ), ...$content] );
    }


    /**
     * Adds the custom link of an image block, which isn't available for images, as text.
     *
     * Links to the image file or the attachment page are skipped, the caption of an image-text element
     * becomes the link if it's a single line, otherwise the link is added after the text.
     *
     * @param array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null $result Content of the image block
     * @param array<mixed> $attrs Attributes of the image block with the "linkDestination"
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null Content with the link
     */
    protected function imageLink( ?array $result, string $html, array $attrs ): ?array
    {
        if( !$result || ( $attrs['linkDestination'] ?? '' ) !== 'custom'
            || !preg_match( '/<a\b[^>]*\bhref=["\']([^"\']+)["\'][^>]*>\s*<img\b([^>]*)>/i', $html, $m ) )
        {
            return $result;
        }

        $label = preg_match( '/\balt=["\']([^"\']*)["\']/i', $m[2], $alt ) && trim( $alt[1] ) !== '' ? $alt[1] : $m[1];
        $link = $this->text( '<p><a href="' . $m[1] . '">' . $label . '</a></p>' )['elements'][0] ?? null;

        if( ( $link['type'] ?? null ) !== 'text' || !is_string( $markdown = $link['data']['text'] ?? null ) ) {
            return $result;
        }

        $first = $result['elements'][0] ?? [];
        $text = $first['data']['text'] ?? null;

        if( ( $first['type'] ?? null ) !== 'image-text' || !is_string( $text ) ) {
            $result['elements'][] = $link;
            return $result;
        }

        $result['elements'][0]['data']['text'] = !str_contains( $text, "\n" ) && !str_contains( $text, '](' ) && preg_match( '/\]\((.*)\)$/s', $markdown, $url )
            ? '[' . $text . '](' . $url[1] . ')' : $text . "\n\n" . $markdown;

        return $result;
    }


    /**
     * Parses a tip/note/caution div into a styled text element.
     *
     * @return ContentElements|null
     */
    protected function parseNoticeBlock( string $block ): ?array
    {
        if( !preg_match( '/^<div class="(tip|note|caution)">(.*?)<\/div>$/is', $block, $m ) ) {
            return null;
        }

        $label = '<strong>' . ( $m[1] === 'caution' ? 'Warning' : ucfirst( $m[1] ) ) . ':</strong>';
        $html = trim( $m[2] );

        // texts without paragraphs are split into paragraphs at blank lines like by wpautop()
        if( !preg_match( '/<(?:' . self::AUTOP_BLOCKS . ')\b/i', $html ) ) {
            $html = implode( '', array_map( fn( $part ) => '<p>' . $this->autop( $part ) . '</p>', preg_split( '/\n\s*\n/', $html ) ?: [] ) );
        }

        // the label starts the first paragraph, the paragraphs, links and lists of the notice are kept
        $text = preg_match( '/^<p\b[^>]*>/i', $html ) ? $this->result( preg_replace( '/^<p\b[^>]*>/i', '$0' . $label . ' ', $html, 1 ), $html ) : '<p>' . $label . '</p>' . $html;

        return $this->text( $text );
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

        return Elements::fileContent( 'image', $fileId );
    }


    /**
     * Parses an HTML table into a table element.
     *
     * @return ContentElements|null
     */
    protected function parseTableBlock( string $block ): ?array
    {
        if( !preg_match( '/<table[^>]*>(.*?)<\/table>/is', $block, $m ) ) {
            return null;
        }

        $rows = [];
        $header = '';
        $plain = fn( string $html ) => trim( Html::decode( strip_tags( $this->result( preg_replace( '/<br\s*\/?>/i', ' ', $html ), $html ) ) ) );
        // table cells are inline Markdown, so line breaks and paragraphs become spaces
        $cell = fn( string $html ) => trim( $this->result( preg_replace( '/\s*\\?\n\s*/', ' ', $md = Markdown::fromHtml( $this->result( preg_replace( '/<br\s*\/?>/i', ' ', $html ), $html ) ) ), $md ) );
        // cells spanning several columns or rows are followed by empty cells, so the columns of the rows match
        $section = function( string $html ) use ( $cell ): array {
            preg_match_all( '/<tr[^>]*>(.*?)<\/tr>/is', $html, $trs );
            $spans = []; // remaining rows of the cells spanning several rows by column
            $rows = [];

            foreach( $trs[1] as $tr )
            {
                preg_match_all( '/<t[hd]\b([^>]*)>(.*?)<\/t[hd]>/is', $tr, $cells, PREG_SET_ORDER );
                $result = [];
                $col = 0;

                $spanned = function( ?int $end = null ) use ( &$spans, &$result, &$col ): void {
                    while( ( $spans[$col] ?? 0 ) > 0 || ( $end !== null && $col < $end ) )
                    {
                        if( ( $spans[$col] ?? 0 ) > 0 ) {
                            $spans[$col]--;
                        }
                        $result[] = '';
                        $col++;
                    }
                };

                foreach( $cells as $c )
                {
                    $spanned();
                    $result[] = $cell( $c[2] );
                    $colspan = preg_match( '/\scolspan=["\']?(\d+)/i', $c[1], $s ) ? min( max( (int) $s[1], 1 ), 100 ) : 1;
                    // "rowspan=0" spans the remaining rows of the section
                    $rowspan = preg_match( '/\srowspan=["\']?(\d+)/i', $c[1], $s ) ? min( (int) $s[1] ?: 100, 100 ) : 1;
                    array_push( $result, ...array_fill( 0, $colspan - 1, '' ) );

                    for( $i = 0; $i < $colspan; $i++, $col++ ) {
                        $spans[$col] = $rowspan - 1;
                    }
                }

                // cells spanning from the rows above after the last cell of the row
                $spanned( ( $last = array_keys( array_filter( $spans ) ) ) ? max( $last ) + 1 : null );

                if( $result ) {
                    $rows[] = $result;
                }
            }

            return $rows;
        };

        if( preg_match( '/<thead[^>]*>(.*?)<\/thead>/is', $m[1], $thead ) ) {
            $header = 'row';
            array_push( $rows, ...$section( $thead[1] ) );
        }

        // the rows of the table footer are added after the body rows like they are shown
        $foot = preg_match( '/<tfoot[^>]*>(.*?)<\/tfoot>/is', $m[1], $tfoot ) ? $tfoot[1] : '';
        $body = preg_match( '/<tbody[^>]*>(.*?)<\/tbody>/is', $m[1], $tbody ) ? $tbody[1]
            : $this->result( preg_replace( '/<thead[^>]*>.*?<\/thead>|<tfoot[^>]*>.*?<\/tfoot>/is', '', $m[1] ), $m[1] );

        array_push( $rows, ...$section( $body ), ...$section( $foot ) );

        if( empty( $rows ) ) {
            return null;
        }

        // tables of the Gutenberg editor have a figcaption next to the table instead of a caption
        $title = '';
        if( preg_match( '/<caption[^>]*>(.*?)<\/caption>/is', $m[1], $cap ) || preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/is', $block, $cap ) ) {
            $title = $plain( $cap[1] );
        }

        return Elements::content( Elements::create( 'table', [
            'title' => $title,
            'header' => $header,
            'table' => $rows,
        ] ) );
    }


    /**
     * Parses a video block into a video element or an HTML element with the player of YouTube and Vimeo videos.
     *
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    protected function parseVideoBlock( string $block ): ?array
    {
        $url = null;

        // Try <video> tag, then iframe/embed src attribute
        if( preg_match( '/<video[^>]+src=["\']([^"\']+)["\']/', $block, $m ) ) {
            $url = $m[1];
        } elseif( preg_match( '/<iframe\b[^>]*>/i', $block, $m ) ) {
            // iframes of other pages are kept like the ones of Custom HTML blocks
            return $this->iframe( $m[0] );
        }

        // Try plain-text URL (YouTube/Vimeo embeds have URL as text content), also mobile, short, live and player URLs
        if( !$url && preg_match_all( '/https?:\/\/[^\s<>"\']+/i', $block, $m ) ) {
            $url = current( preg_grep( self::VIDEO, $m[0] ) ?: [] ) ?: null;
        }

        // YouTube and Vimeo videos can't be shown by video elements, unknown ones become links
        if( $url && preg_match( self::VIDEO, $this->iframeSource( Html::decode( $url ) ) ) ) {
            return $this->player( $url );
        }

        // the HTML entities like "&amp;" in the URL are decoded like by the browser
        if( $url && ( $fileId = ( $this->file )( Html::decode( $url ), '' ) ) ) {
            return Elements::fileContent( 'video', $fileId );
        }

        return null;
    }


    /**
     * Returns an HTML element with the player iframe for YouTube and Vimeo videos.
     *
     * Their videos can't be shown by video elements, so they are embedded like WordPress does.
     *
     * @param string $url URL of the video page or player, may contain HTML entities
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null Content or NULL for other URLs
     */
    protected function player( string $url ): ?array
    {
        $url = $this->iframeSource( Html::decode( $url ) );

        // pages of the videos can't be shown in iframes, only their players
        if( !preg_match( self::VIDEO, $url ) || !( $src = preg_match( '#\.com/(?:embed|video)/#i', $url ) ? $url : Embeds::player( $url ) ) ) {
            return null;
        }

        return Embeds::iframeContent( $src );
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
        $pattern = '/<!--\s*wp:(' . self::NAME . ')(\s+' . self::ATTRS . ')?\s*(\/)?-->/is';
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
                // Self-closing: <!-- wp:type /-->, footnotes, not replaced reusable blocks and dynamic blocks without content
                $blocks[] = ['type' => $type, 'attrs' => $attrs, 'html' => ''];
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
                if( $type === 'group' || $type === 'tab-panels' || $this->wrapsBlocks( $type, $content ) ) {
                    // groups and layout blocks of plugins only wrap other blocks, so the inner blocks are used without the wrapping tags
                    $inner = $this->splitGutenbergBlocks( $content );
                    array_push( $blocks, ...array_filter( $inner, fn( $block ) => $block['type'] !== null || !$this->wrapper( $block['html'] ) ) );
                } elseif( $type === 'tabs' ) {
                    array_push( $blocks, ...$this->splitTabsBlock( $content ) );
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
     * Tests if the block of a plugin only wraps other blocks like the row and column blocks of page builders.
     *
     * Plugin blocks converted by the importer aren't containers.
     */
    protected function wrapsBlocks( string $type, string $content ): bool
    {
        return str_contains( $type, '/' ) && !str_starts_with( $type, 'core/' ) && $type !== 'syntaxhighlighter/code' && preg_match( '/<!--\s*wp:/', $content ) === 1;
    }


    /**
     * Splits the content of a tabs block into the bold label of each tab panel followed by its inner blocks.
     *
     * The labels are the ones of the tab panels or the buttons of the tab list, which isn't used otherwise.
     *
     * @return array<int, array{type: string|null, attrs: array<mixed>, html: string}>
     */
    protected function splitTabsBlock( string $html ): array
    {
        $blocks = $labels = [];
        $index = 0;

        foreach( $this->splitGutenbergBlocks( $html ) as $entry )
        {
            if( $entry['type'] === 'tab-list' )
            {
                preg_match_all( '/<button\b[^>]*>(.*?)<\/button>/is', $entry['html'], $m );
                $labels = $m[1];
                continue;
            }

            if( $entry['type'] !== 'tab-panel' )
            {
                if( $entry['type'] !== null || !$this->wrapper( $entry['html'] ) ) {
                    $blocks[] = $entry;
                }
                continue;
            }

            $label = is_string( $l = $entry['attrs']['label'] ?? null ) && trim( $l ) !== '' ? $l : ( $labels[$index] ?? '' );
            $label = trim( strip_tags( $label, '<strong><b><em><i><a><code>' ) );
            $index++;

            if( $label !== '' ) {
                $blocks[] = ['type' => 'tab-label', 'attrs' => [], 'html' => '<p><strong>' . $label . '</strong></p>'];
            }

            $inner = $this->splitGutenbergBlocks( $entry['html'] );
            array_push( $blocks, ...array_filter( $inner, fn( $block ) => $block['type'] !== null || !$this->wrapper( $block['html'] ) ) );
        }

        return $blocks;
    }


    /**
     * Splits classic content into logical blocks at heading, pre/notice div, details, image and paragraph boundaries.
     *
     * @return string[]
     */
    #[\Override]
    protected function splitIntoBlocks( string $html ): array
    {
        $pattern = '/(<h[1-6][^>]*>.*?<\/h[1-6]>|<pre[^>]*>.*?<\/pre>|<details\b[^>]*>.*?<\/details>|<div\s+class="(?:tip|note|caution)"[^>]*>.*?<\/div>)/is';
        $parts = preg_split( $pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

        $blocks = [];

        foreach( $parts as $part )
        {
            $part = trim( $part );
            if( empty( $part ) ) {
                continue;
            }

            if( preg_match( '/^<(h[1-6]|pre|details\b|div\s+class="(?:tip|note|caution)")/i', $part ) ) {
                $blocks[] = $part;
                continue;
            }

            // lists, quotes and tables aren't split by blank lines or images in between like by wpautop()
            $containers = [];
            $part = $this->result( preg_replace_callback( '#<(ul|ol|dl|blockquote|table)\b[^>]*>(?:[^<]++|<(?!/?\1\b)|(?R))*</\1>#is', function( $m ) use ( &$containers ) {
                $containers[] = $this->container( $m[0], $m[1] );
                return "\x00" . ( count( $containers ) - 1 ) . "\x00";
            }, $part ), $part );

            $subParts = preg_split( '/(\n\s*\n|(?:<a[^>]*>\s*<img[^>]+>\s*<\/a>)|(?:<img[^>]+\/?>))/is', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY ) ?: [];

            foreach( $subParts as $sub ) {
                $sub = trim( $this->result( preg_replace_callback( '/\x00(\d+)\x00/', fn( $m ) => $containers[(int) $m[1]] ?? '', $sub ), $sub ) );
                if( !empty( $sub ) ) {
                    $blocks[] = $sub;
                }
            }
        }

        return $blocks;
    }


    /**
     * Removes the blank lines from a list, quote or table, the texts in quotes separated by blank lines become paragraphs.
     *
     * @param string $html HTML of the list, quote or table including the outer tag
     * @param string $tag Name of the outer tag
     */
    protected function container( string $html, string $tag ): string
    {
        if( strtolower( $tag ) !== 'blockquote' || !preg_match( '/^(<blockquote\b[^>]*>)(.*)(<\/blockquote>)$/is', $html, $m ) ) {
            return $this->result( preg_replace( '/\n\s*\n/', "\n", $html ), $html );
        }

        $paras = array_filter( array_map( 'trim', preg_split( '/\n\s*\n/', $m[2] ) ?: [] ) );
        $paras = array_map( fn( $para ) => preg_match( '/^<(?:p|ul|ol|dl|h[1-6]|pre|div|blockquote|table|figure|cite)\b/i', $para ) ? $para : '<p>' . $para . '</p>', $paras );

        return $m[1] . implode( "\n", $paras ) . $m[3];
    }


    /**
     * Converts a shortcode of WordPress core into content.
     *
     * @param string $tag Name of the shortcode, one of Shortcode::TAGS
     * @param array<string, string> $attrs Attributes of the shortcode
     * @param string $content Content of enclosing shortcodes like the image and caption of "[caption]"
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function shortcode( string $tag, array $attrs, string $content ): ?array
    {
        return match( $tag ) {
            'caption', 'wp_caption' => $this->captionShortcode( $attrs, $content ),
            'gallery' => $this->galleryShortcode( $attrs ),
            'embed' => $this->embedShortcode( $attrs, $content ),
            'video', 'audio' => $this->mediaShortcode( $tag, $attrs ),
            'playlist' => $this->playlistShortcode(),
            'code', 'sourcecode' => $this->codeShortcode( $attrs, $content ),
            default => null,
        };
    }


    /**
     * Converts a code shortcode of WordPress.com and SyntaxHighlighter like "[code language="php"]" into a code element.
     *
     * The code is kept as it is without the line breaks of wpautop(), only the HTML entities of the visual editor are decoded.
     *
     * @param array<string, string> $attrs Attributes of the shortcode with the language in "language" or "lang"
     * @param string $content Code of the enclosing shortcode
     * @return ContentElements|null
     */
    protected function codeShortcode( array $attrs, string $content ): ?array
    {
        $code = html_entity_decode( str_replace( "\r\n", "\n", $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $code = rtrim( $this->result( preg_replace( '/^(?:[ \t]*\n)+/', '', $code ), $code ) );

        if( trim( $code ) === '' ) {
            return null;
        }

        return Elements::content( Elements::create( 'code', [
            'language' => $this->codeLanguage( $attrs['language'] ?? $attrs['lang'] ?? '' ),
            'text' => $code,
        ] ) );
    }


    /**
     * Converts a caption shortcode into an image or image-text element like img_caption_shortcode() of WordPress.
     *
     * The caption is the "caption" attribute or the text after the image in the content.
     *
     * @param array<string, string> $attrs Attributes of the shortcode, "align" is the position of the image
     * @param string $content Image with optional link and caption text
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function captionShortcode( array $attrs, string $content ): ?array
    {
        $caption = $attrs['caption'] ?? null;

        if( $caption === null && preg_match( '#((?:<a [^>]+>\s*)?<img [^>]+>(?:\s*</a>)?)(.*)#is', $content, $m ) ) {
            [$content, $caption] = [$m[1], trim( $m[2] )];
        }

        $position = match( $attrs['align'] ?? '' ) {
            'alignleft' => 'start',
            'alignright' => 'end',
            default => null,
        };

        $result = $this->imageText( preg_match( self::IMAGE_PATTERN, $content ) ? $this->image( $content ) : null, (string) $caption, $position );
        return $result['elements'] ? $result : null;
    }


    /**
     * Converts the URL of embedded content into a video player, a video or audio element or a link.
     *
     * Like the embed handlers of WordPress, YouTube and Vimeo videos are shown by their players and URLs ending with
     * the extensions of audio and video files are played like by wp_embed_handler_audio() and wp_embed_handler_video().
     *
     * @param string $url URL of the embedded content
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function embed( string $url ): ?array
    {
        if( $result = $this->parseVideoBlock( $url ) ) {
            return $result;
        }

        $src = Html::decode( $url );

        foreach( Shortcode::EXTENSIONS as $tag => $exts )
        {
            if( preg_match( '#^https?://.+?\.(?:' . implode( '|', $exts ) . ')$#i', $src ) ) {
                return $this->mediaShortcode( $tag, ['src' => $src] );
            }
        }

        return $this->link( $url );
    }


    /**
     * Converts an embed shortcode into a video player, a video or audio element or a link, see embed().
     *
     * @param array<string, string> $attrs Attributes of the shortcode, "src" contains the URL if there's no content like in WP_Embed::shortcode()
     * @param string $content URL of the embedded content
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function embedShortcode( array $attrs, string $content ): ?array
    {
        $url = trim( strip_tags( $content ) ) ?: trim( $attrs['src'] ?? '' );
        return $url !== '' ? $this->embed( $url ) : null;
    }


    /**
     * Converts a gallery shortcode with attachment IDs into a slideshow element or an image element for one image.
     *
     * Galleries without IDs show the images attached to the post, their IDs are added by Reader::galleries() before.
     *
     * @param array<string, string> $attrs Attributes of the shortcode, "ids" or "include" contain the attachment IDs
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function galleryShortcode( array $attrs ): ?array
    {
        if( !( $ids = Shortcode::ids( $attrs ) ) )
        {
            if( $this->warn ) {
                ( $this->warn )( '  Gallery shortcode without images skipped' );
            }
            return null;
        }

        // the images are imported by their attachment IDs, see Media::image()
        $html = implode( '', array_map( fn( $id ) => '<img class="wp-image-' . $id . '" alt="" />', $ids ) );
        return $this->parseGalleryBlock( $html ) ?? $this->parseStandaloneImageBlock( $html );
    }


    /**
     * Skips playlist shortcodes whose files aren't found, the others are replaced by Reader::playlists() before.
     */
    protected function playlistShortcode(): null
    {
        if( $this->warn ) {
            ( $this->warn )( '  Playlist shortcode without files skipped' );
        }

        return null;
    }


    /**
     * Returns a text element with a link to the URL.
     *
     * @return ContentElements|null
     */
    protected function link( string $url ): ?array
    {
        $url = htmlspecialchars( Html::decode( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return $this->text( '<p><a href="' . $url . '">' . $url . '</a></p>' );
    }


    /**
     * Converts a video or audio shortcode into a video or audio element like wp_video_shortcode() and wp_audio_shortcode().
     *
     * Files which WordPress doesn't play become links like in WordPress, see Shortcode::playable().
     *
     * @param string $tag "video" or "audio"
     * @param array<string, string> $attrs Attributes of the shortcode, see Shortcode::source()
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    protected function mediaShortcode( string $tag, array $attrs ): ?array
    {
        // shortcodes without URL show the first file attached to the post, which is added by Reader::playlists() before
        if( ( $url = Shortcode::source( $tag, $attrs ) ) === '' )
        {
            if( $this->warn ) {
                ( $this->warn )( '  ' . ucfirst( $tag ) . ' shortcode without file skipped' );
            }
            return null;
        }

        // WordPress shows a link instead of a player for files of other types
        if( !Shortcode::playable( $tag, $attrs, $url ) ) {
            return $this->link( $url );
        }

        // the video shortcode plays YouTube and Vimeo videos by their players
        if( $tag === 'video' && preg_match( self::VIDEO, $this->iframeSource( Html::decode( $url ) ) ) ) {
            return $this->player( $url ) ?? $this->link( $url );
        }

        return ( $fileId = ( $this->file )( Html::decode( $url ), '' ) ) ? Elements::fileContent( $tag, $fileId ) : $this->link( $url );
    }


    /**
     * Strips all WordPress block comments from HTML.
     */
    protected function stripWpComments( string $html ): string
    {
        return trim( $this->result( preg_replace( '/<!--\s*\/?wp:[^>]*-->/i', '', $html ), $html ) );
    }
}
