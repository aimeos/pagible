<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Statamic;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Embeds;
use Aimeos\Cms\Import\Html;
use Aimeos\Cms\Utils;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension;
use League\CommonMark\MarkdownConverter;


/**
 * Converts Statamic Bard, Replicator, Markdown and HTML field values into Pagible content elements.
 *
 * @phpstan-import-type ContentElements from Elements
 * @phpstan-import-type TextField from Reader
 * @phpstan-import-type ArrayField from Reader
 */
class Content
{
    /** Default fields of the entries containing the content */
    public const FIELDS = ['body', 'content', 'article', 'text', 'page_builder', 'page_sections', 'blocks'];

    /** Keys of the fields whose values are assets, used by the featured image, Bard images and sets */
    protected const ASSET_KEYS = ['featured_image', 'hero_image', 'image', 'cover', 'photo', 'file', 'images', 'photos', 'gallery', 'src', 'og_image'];

    /** Types of the Bard nodes at the top level of Bard content */
    protected const BARD_TYPES = ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock', 'set', 'image', 'horizontalRule', 'table', 'doc'];

    /** Keys of Bard nodes, Replicator sets have other fields */
    protected const BARD_KEYS = ['type', 'attrs', 'content', 'marks', 'text'];

    /** Types of the Bard nodes at the top level of inline Bard content, which has no paragraphs */
    protected const INLINE_TYPES = ['text', 'hardBreak'];

    /** Regular expression matching HTML starting with a block tag, which is converted by the HTML converter */
    protected const BLOCK_HTML_PATTERN = '/^<(?:p|div|h[1-6]|ul|ol|dl|table|figure|blockquote|pre|section|article|img|iframe|hr)\b/i';

    /** Regular expression matching the URL schemes which can be linked */
    protected const LINK_PATTERN = '/^(?:https?:\/\/|mailto:|tel:|[\/#?])/i';

    /** Regular expression matching an HTML tag, autolinks like <https://...> aren't HTML tags */
    protected const TAG_PATTERN = '/<[a-z][a-z0-9-]*(?:[\s\/][^>]*)?>/i';

    /** Regular expression matching Markdown images, which are imported as files by the HTML converter */
    protected const IMAGE_MD_PATTERN = '/!\[[^\]\n]*\]\(/';

    /** Regular expression matching the file extensions of images */
    protected const IMAGE_PATTERN = '/\.(?:avif|gif|jpe?g|png|svg|webp)$/i';

    /** Regular expression matching paths and URLs of files with an extension */
    protected const FILE_PATTERN = '/\.[a-z0-9]{2,5}$/i';

    /** Regular expression matching the file extensions of videos */
    protected const VIDEO_PATTERN = '/\.(?:mp4|m4v|webm|ogv|mov)$/i';

    /** Regular expression matching the file extensions of audio files */
    protected const AUDIO_PATTERN = '/\.(?:mp3|m4a|aac|oga|ogg|wav|flac)$/i';

    /** Pagible languages of the Statamic Code field modes which differ */
    protected const CODE_LANGUAGES = [
        'htmlmixed' => 'html', 'text/x-java' => 'java', 'gfm' => 'markdown', 'jsx' => 'javascript',
        'yaml-frontmatter' => 'yaml', 'text/x-objectivec' => 'objectivec', 'clike' => 'c',
    ];

    /** Text fields of sets and groups imported as headings */
    protected const HEADING_FIELDS = ['title', 'heading', 'headline'];

    /** Fields of sets and groups containing text, also if their type isn't known from the blueprint or they are Text fields */
    protected const TEXT_FIELDS = ['text', 'body', 'content', 'intro', 'description', 'subtitle', 'subheading', 'summary', 'lead', 'teaser'];

    /** Types of the fields of sets and groups whose text is imported whatever their name is */
    protected const CONTENT_TYPES = ['markdown', 'textarea', 'bard', 'code'];

    /** Regular expression matching the values only stored by Link fields, used if the blueprint is unknown */
    protected const LINK_VALUE_PATTERN = '/^(?:(?:statamic:\/\/)?(?:entry|asset)::\S+|@child)$/';

    /** Text fields of sets and groups used as label of the Link field in the same set or group */
    protected const LABEL_FIELDS = ['label', 'link_text', 'link_label', 'button_text', 'button_label', 'cta_text', 'cta_label'];

    /** Keys of Replicator sets which aren't fields */
    protected const SET_KEYS = ['type', 'id', 'enabled'];

    /** Regular expression matching footnotes, attributes and description lists of Statamic Markdown which aren't GitHub flavored Markdown */
    protected const EXTENDED_PATTERN = '/\[\^[^\]\s]+\]|\{:?[ \t]*(?:[.#][\w-]|[\w-]+=)[^}\n]*\}|^[^\s:][^\n]*\n(?:[ \t]*\n)?:[ \t]+\S/m';

    /**
     * Regular expressions matching the start of the HTML blocks in Markdown and their end, empty if they end at a blank line.
     *
     * @see https://spec.commonmark.org/0.31.2/#html-blocks
     */
    protected const HTML_BLOCKS = [
        '/^ {0,3}<(?:script|pre|style|textarea)(?:\s|>|$)/i' => '/<\/(?:script|pre|style|textarea)>/i',
        '/^ {0,3}<!--/' => '/-->/',
        '/^ {0,3}<\?/' => '/\?>/',
        '/^ {0,3}<!\[CDATA\[/' => '/\]\]>/',
        '/^ {0,3}<![a-z]/i' => '/>/',
        '/^ {0,3}<\/?(?:address|article|aside|base|basefont|blockquote|body|caption|center|col|colgroup|dd|details|dialog|dir|div|dl|dt|fieldset|figcaption|figure|footer|form|frame|frameset|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|menuitem|nav|noframes|ol|optgroup|option|p|param|search|section|summary|table|tbody|td|tfoot|th|thead|title|tr|track|ul)(?:\s|\/?>|$)/i' => '',
    ];

    /** Regular expression matching a line with a single open or closing tag, which starts an HTML block if it doesn't continue a paragraph */
    protected const HTML_LINE_PATTERN = '/^ {0,3}(?:<[a-z][a-z0-9-]*(?:\s+[a-z_:][\w.:-]*(?:\s*=\s*(?:[^\s"\'=<>`]+|\'[^\']*\'|"[^"]*"))?)*\s*\/?>|<\/[a-z][a-z0-9-]*\s*>)\s*$/i';

    protected Bard $bard;
    protected ?Html $html = null;
    /** @var array<string, string> Asset containers of the fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $containers = [];
    /** @var array<string, bool> Grid fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $grids = [];
    /** @var array<string, TextField> Types and Markdown settings of the text fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $texts = [];
    /** @var array<string, bool> Video fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $videos = [];
    /** @var array<string, bool> Link fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $links = [];
    /** @var array<string, bool> If Link and Bard fields select entries across sites by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $across = [];
    /** @var bool If links to entries refer to the entries of the site of the converted entry */
    protected bool $localize = true;
    /** @var array<string, bool> List fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $lists = [];
    /** @var array<string, ArrayField> Labels of the keys and column headers of the Array fields by field key like "set.field" or "field" from the blueprint of the entry */
    protected array $arrays = [];


    /**
     * Initializes the converter.
     *
     * @param \Closure(string, string=): ?string $asset Imports the asset like "container::path" or its URL with the alt text and returns the file ID
     * @param \Closure(string, bool=): ?string $reference Returns the URL of a Statamic reference like "entry::ID" or "asset::container::path" or NULL if it's unknown,
     *  entries are localized to the site of the converted entry if the second parameter is TRUE
     * @param string $container Handle of the asset container of plain asset paths
     * @param array<int, string> $fields Fields of the entries containing the content
     * @param (\Closure(string, string): void)|null $warn Shows a warning only once for the key, e.g. for content in fields which aren't imported
     */
    public function __construct( protected \Closure $asset, protected \Closure $reference, protected string $container = 'assets',
        protected array $fields = self::FIELDS, protected ?\Closure $warn = null )
    {
        $this->bard = new Bard( fn( string $href ) => $this->link( $href ) );
    }


    /**
     * Returns the asset of a field value like "container::path" or its URL.
     *
     * Bard references the assets by their container and path like "asset::assets::images/a.jpg" or
     * "statamic://asset::assets::images/a.jpg" while asset fields contain the path relative to the
     * container of the field like "images/a.jpg".
     *
     * @param string $value Asset reference, path or URL
     * @param string $container Handle of the asset container of plain paths
     * @return string Asset like "assets::images/a.jpg", URLs and absolute paths are unchanged, empty if there's no asset
     */
    public static function asset( string $value, string $container ): string
    {
        $value = trim( (string) preg_replace( '/^statamic:\/\//', '', trim( $value ) ) );

        if( preg_match( '/^asset::([^:\/]+)::(.+)$/s', $value, $match ) ) {
            return $match[1] . '::' . ltrim( $match[2], '/' );
        }

        if( $value === '' || preg_match( '/^[a-z][a-z0-9+.-]*:/i', $value ) || str_starts_with( $value, '/' ) ) {
            return $value;
        }

        return $container . '::' . $value;
    }


    /**
     * Returns the asset container of the field from the blueprint of the entry or the default container.
     *
     * @param int|string|null $field Field handle
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return string Container handle
     */
    public function container( int|string|null $field, ?string $set = null ): string
    {
        return is_string( $field ) ? $this->setting( $this->containers, $field, $set ) ?? $this->container : $this->container;
    }


    /**
     * Sets the asset containers of the asset fields from the blueprint of the converted entry.
     *
     * @param array<string, string> $containers Container handles by field handle
     * @return static Same object for fluent calls
     */
    public function containers( array $containers ): static
    {
        $this->containers = $containers;
        return $this;
    }


    /**
     * Sets the Grid fields from the blueprint of the converted entry, their content isn't imported.
     *
     * @param array<string, bool> $grids TRUE by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function grids( array $grids ): static
    {
        $this->grids = $grids;
        return $this;
    }


    /**
     * Sets the types and Markdown settings of the text fields from the blueprint of the converted entry.
     *
     * @param array<string, TextField> $texts Field type and settings by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function texts( array $texts ): static
    {
        $this->texts = $texts;
        return $this;
    }


    /**
     * Sets the Video fields from the blueprint of the converted entry.
     *
     * @param array<string, bool> $videos TRUE by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function videos( array $videos ): static
    {
        $this->videos = $videos;
        return $this;
    }


    /**
     * Sets the Link fields from the blueprint of the converted entry.
     *
     * @param array<string, bool> $links TRUE by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function links( array $links ): static
    {
        $this->links = $links;
        return $this;
    }


    /**
     * Sets if the Link and Bard fields from the blueprint of the converted entry select entries across sites.
     *
     * @param array<string, bool> $across TRUE if entries are selected across sites, FALSE if not by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function across( array $across ): static
    {
        $this->across = $across;
        return $this;
    }


    /**
     * Sets the List fields from the blueprint of the converted entry.
     *
     * @param array<string, bool> $lists TRUE by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function lists( array $lists ): static
    {
        $this->lists = $lists;
        return $this;
    }


    /**
     * Sets the Array fields from the blueprint of the converted entry.
     *
     * @param array<string, ArrayField> $arrays Labels by key and column headers of the arrays by field key like "set.field" or "field"
     * @return static Same object for fluent calls
     */
    public function arrays( array $arrays ): static
    {
        $this->arrays = $arrays;
        return $this;
    }


    /**
     * Returns the assets which are probably imported for the entry data.
     *
     * The values of all fields named like the asset fields or which are asset fields in the blueprint are
     * returned, so the files can be fetched at once before the content is converted. Missed assets are
     * looked up when they are imported.
     *
     * @param array<mixed> $data Decoded entry data
     * @param string|null $set Handle of the Replicator or Bard set whose fields are the data
     * @return array<int, string> Unique assets like "container::path" or their URLs
     */
    public function assets( array $data, ?string $set = null ): array
    {
        $assets = [];

        foreach( $data as $key => $value )
        {
            if( is_string( $key ) && ( in_array( $key, static::ASSET_KEYS, true ) || $this->setting( $this->containers, $key, $set ) !== null ) )
            {
                foreach( (array) $value as $path )
                {
                    if( is_string( $path ) && ( $asset = self::asset( $path, $this->container( $key, $set ) ) ) !== '' ) {
                        $assets[] = $asset;
                    }
                }
            }

            if( is_array( $value ) ) {
                $assets = array_merge( $assets, $this->assets( $value, is_string( $value['type'] ?? null ) ? $value['type'] : $set ) );
            }
        }

        return array_values( array_unique( $assets ) );
    }


    /**
     * Converts the content fields of the entry data into Pagible content elements.
     *
     * Other fields with Bard or Replicator content aren't imported, a warning is shown for each of them once.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @return ContentElements
     */
    public function content( array $data ): array
    {
        $contents = [];

        foreach( $this->fields as $field )
        {
            if( !empty( $data[$field] ) ) {
                $contents[] = is_array( $data[$field] ) && $this->isTable( $data[$field] )
                    ? $this->table( $data[$field], !empty( $data['first_row_headers'] ) )
                    : $this->elements( $data[$field], $field );
            }
        }

        foreach( $data as $field => $value )
        {
            if( $this->warn && is_array( $value ) && !in_array( $field, $this->fields, true ) && ( $this->isBard( $value ) || $this->isReplicator( $value ) ) ) {
                ( $this->warn )( "field:{$field}", "Field \"{$field}\" contains content which isn't imported, add it to the --fields option to import it" );
            }
        }

        return Elements::merge( ...$contents );
    }


    /**
     * Converts a Statamic field value into Pagible content elements.
     *
     * The rows of Grid fields aren't imported, a warning is shown once instead. Table fields become table elements
     * and the fields of Group fields are imported in their order. Antlers tags in fields allowing Antlers aren't
     * evaluated and are imported as text.
     *
     * @param mixed $value Field value
     * @param string|null $field Field handle to get the type of text fields from the blueprint
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return ContentElements
     */
    public function elements( mixed $value, ?string $field = null, ?string $set = null ): array
    {
        // Statamic links to the entries of the current site unless the field selects entries across sites
        if( $field !== null && ( $localize = !$this->setting( $this->across, $field, $set ) ) !== $this->localize )
        {
            $this->localize = $localize;

            try {
                return $this->elements( $value, $field, $set );
            } finally {
                $this->localize = !$localize;
            }
        }

        if( $field !== null && $this->warn && ( $this->setting( $this->texts, $field, $set )['antlers'] ?? false )
            && str_contains( is_string( $value ) ? $value : (string) json_encode( $value ), '{{' ) )
        {
            ( $this->warn )( "antlers:{$field}", "Field \"{$field}\" contains Antlers tags, they aren't evaluated and are imported as text" );
        }

        // Code fields store the code or the code and its language if the language can be selected
        if( is_string( $value ) && $field !== null && ( $settings = $this->setting( $this->texts, $field, $set ) ) !== null && $settings['type'] === 'code' ) {
            return $this->codeContent( $value, $settings['mode'] ?? 'htmlmixed' );
        }

        if( is_array( $value ) && $this->isCode( $value ) ) {
            return $this->codeContent( (string) $value['code'], is_string( $value['mode'] ?? null ) ? $value['mode'] : '' );
        }

        if( is_array( $value ) && $this->isTable( $value ) ) {
            return $this->table( $value );
        }

        if( is_array( $value ) && $value !== [] && ( $field !== null && $this->setting( $this->grids, $field, $set ) || $this->isGrid( $value ) ) )
        {
            if( $this->warn ) {
                ( $this->warn )( 'grid:' . ( $field ?? '' ), 'Grid field' . ( $field !== null ? " \"{$field}\"" : '' ) . ' isn\'t imported, only Bard, Replicator, Markdown, Text, Textarea and HTML fields' );
            }
            return Elements::content();
        }

        if( is_array( $value ) && $this->isBard( $value ) ) {
            return $this->bard( $this->unwrap( $value ) );
        }

        if( is_array( $value ) && $this->isReplicator( $value ) ) {
            return Elements::merge( ...array_map( fn( $set ) => is_array( $set ) ? $this->replicatorSet( $set ) : null, $value ) );
        }

        if( is_string( $value ) ) {
            return $this->markdownOrHtml( $value, $field, $set );
        }

        // Group fields store their fields by handle
        if( is_array( $value ) && $value !== [] && !array_is_list( $value ) ) {
            return $this->group( $value, $field, $set );
        }

        return Elements::content();
    }


    /**
     * Returns the URL of the link target or NULL if it can't be linked.
     *
     * Bard links to entries and assets like "statamic://entry::ID" are resolved, only links to web pages,
     * email addresses, phone numbers and relative URLs are kept. Links to entries refer to the entries of the
     * site of the converted entry like in Statamic unless the field selects entries across sites.
     *
     * @param string $href Link target
     * @param bool|null $localize If links to entries refer to the entries of the current site or NULL for the setting of the converted field
     */
    public function link( string $href, ?bool $localize = null ): ?string
    {
        $href = trim( $href );

        if( preg_match( '/^(?:statamic:\/\/)?((?:entry|asset)::.+)$/s', $href, $match ) ) {
            return ( $this->reference )( $match[1], $localize ?? $this->localize );
        }

        return preg_match( static::LINK_PATTERN, $href ) && !str_starts_with( $href, '//' ) ? $href : null;
    }


    /**
     * Converts Bard ProseMirror content into Pagible content elements.
     *
     * @param array<int|string, mixed> $nodes Bard nodes
     * @return ContentElements
     */
    protected function bard( array $nodes ): array
    {
        $contents = [];
        $buffer = '';

        foreach( $nodes as $node )
        {
            if( !is_array( $node ) ) {
                continue;
            }

            // images are inline nodes in Bard paragraphs, the paragraph is split at the images
            if( ( $node['type'] ?? '' ) === 'paragraph' && $this->hasImage( $node, false ) )
            {
                array_push( $contents, ...$this->bardParagraph( $node, $buffer ) );
                continue;
            }

            if( $this->warn && !in_array( $node['type'] ?? '', ['paragraph', 'image', 'set'], true ) && $this->hasImage( $node, true ) ) {
                ( $this->warn )( 'bard:image', "Images in Bard lists, quotes and tables aren't imported, only images in paragraphs" );
            }

            $content = match( $node['type'] ?? '' ) {
                'heading' => Elements::content( Elements::create( 'heading', [
                    'level' => Bard::level( $node['attrs']['level'] ?? 2 ),
                    'title' => trim( (string) preg_replace( '/\s+/', ' ', $this->bard->text( $node ) ) ),
                ] ) ),
                'codeBlock' => Elements::content( Elements::create( 'code', [
                    'language' => $this->language( (string) ( $node['attrs']['language'] ?? '' ) ),
                    'text' => $this->bard->text( $node ),
                ] ) ),
                // disabled sets are hidden by Statamic
                'set' => ( $node['attrs']['enabled'] ?? true ) !== false ? $this->set( (array) ( $node['attrs']['values'] ?? [] ) ) ?? Elements::content() : Elements::content(),
                'image' => $this->bardImage( $node ) ?? Elements::content(),
                'horizontalRule' => Elements::content( Elements::create( 'html', ['text' => '<hr>'] ) ),
                'table' => $this->bardTable( $node ) ?? Elements::content(),
                default => null,
            };

            if( $content === null ) {
                $buffer .= $this->bard->markdown( $node );
                continue;
            }

            $contents[] = $this->flush( $buffer );
            $contents[] = $content;
        }

        $contents[] = $this->flush( $buffer );

        return Elements::merge( ...$contents );
    }


    /**
     * Converts a Bard image node into a Pagible image element.
     *
     * @param array<mixed> $node Bard image node
     * @return ContentElements|null
     */
    protected function bardImage( array $node ): ?array
    {
        $src = $node['attrs']['src'] ?? '';

        if( !is_string( $src ) || ( $src = self::asset( $src, $this->container ) ) === '' ) {
            return null;
        }

        return Elements::imageContent( ( $this->asset )( $src, (string) ( $node['attrs']['alt'] ?? '' ) ) );
    }


    /**
     * Converts a Bard paragraph containing images into text and image elements.
     *
     * The text before an image is added to the buffer and returned with the image element, the text
     * after the last image remains in the buffer.
     *
     * @param array<mixed> $node Bard paragraph node
     * @param string $buffer Markdown text which isn't converted yet
     * @return array<int, ContentElements|null> Text and image elements
     */
    protected function bardParagraph( array $node, string &$buffer ): array
    {
        $contents = [];
        $part = [];

        foreach( (array) ( $node['content'] ?? [] ) as $child )
        {
            if( !is_array( $child ) || ( $child['type'] ?? '' ) !== 'image' )
            {
                $part[] = $child;
                continue;
            }

            if( $part !== [] ) {
                $buffer .= $this->bard->markdown( ['content' => $part] + $node );
                $part = [];
            }

            $contents[] = $this->flush( $buffer );
            $contents[] = $this->bardImage( $child );
        }

        if( $part !== [] ) {
            $buffer .= $this->bard->markdown( ['content' => $part] + $node );
        }

        return $contents;
    }


    /**
     * Converts a Bard table node into a Pagible table element.
     *
     * @param array<mixed> $node Bard table node
     * @return ContentElements|null
     */
    protected function bardTable( array $node ): ?array
    {
        if( !( $table = $this->bard->table( $node ) ) ) {
            return null;
        }

        return Elements::content( Elements::create( 'table', ['title' => '', 'header' => $table['header'], 'table' => $table['rows']] ) );
    }


    /**
     * Returns the accumulated Markdown text as text element and empties the buffer.
     *
     * @param string $buffer Markdown text
     * @return ContentElements|null
     */
    protected function flush( string &$buffer ): ?array
    {
        $text = trim( $buffer );
        $buffer = '';

        return $text !== '' ? Elements::content( $this->textElement( $text ) ) : null;
    }


    /**
     * Tests if the Bard node contains images.
     *
     * @param array<mixed> $node Bard node
     * @param bool $deep TRUE to test all descendants, FALSE to test the child nodes only
     */
    protected function hasImage( array $node, bool $deep ): bool
    {
        foreach( (array) ( $node['content'] ?? [] ) as $child )
        {
            if( is_array( $child ) && ( ( $child['type'] ?? '' ) === 'image' || $deep && $this->hasImage( $child, true ) ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns a code element for the code.
     *
     * @param string $code Code text
     * @param string $lang Language of the code
     * @return ContentElements
     */
    protected function codeContent( string $code, string $lang ): array
    {
        if( trim( $code ) === '' ) {
            return Elements::content();
        }

        return Elements::content( Elements::create( 'code', ['language' => $this->language( $lang ), 'text' => $code] ) );
    }


    /**
     * Returns the Pagible language of the Statamic Code field mode.
     *
     * @param string $mode Code field mode like "htmlmixed" or "php"
     * @return string Pagible language like "html" or "php"
     */
    protected function language( string $mode ): string
    {
        $mode = strtolower( trim( $mode ) );
        return static::CODE_LANGUAGES[$mode] ?? $mode;
    }


    /**
     * Returns the first of the fields which is set.
     *
     * @param array<mixed> $values Fields of the set
     * @param array<int, string> $fields Field handles in the order they are used
     * @return string|null Field handle or NULL if none of the fields is set
     */
    protected function field( array $values, array $fields ): ?string
    {
        foreach( $fields as $field )
        {
            if( isset( $values[$field] ) ) {
                return $field;
            }
        }

        return null;
    }


    /**
     * Converts the fields of a set or group into Pagible content elements in their order.
     *
     * Bard, Replicator, Table, Group, Markdown, Textarea and Code fields are converted, Text fields and fields of
     * unknown type only if they are named like text fields because Text fields often contain button labels,
     * anchors or CSS classes. Text fields named like headings become headings, images of asset fields become
     * image elements with the "alt" or "caption" field of the set, videos and audio files become video and audio
     * elements and other files become file elements. Video fields embed the players or the video files, Link
     * fields become links with the text of their label fields, List fields bullet lists and Array fields tables
     * with the keys or their labels in the first column. Grid fields are skipped because a warning
     * is shown for them already.
     *
     * @param array<mixed> $values Fields by handle
     * @param string|null $set Handle of the Replicator or Bard set containing the fields
     * @return ContentElements
     */
    protected function fields( array $values, ?string $set ): array
    {
        $contents = [];
        // fields of unknown type are Link fields if their values are references only Link fields store
        $links = array_values( array_filter( array_keys( $values ), fn( $field ) => is_string( $field ) && ( $this->setting( $this->links, $field, $set )
            || $this->setting( $this->texts, $field, $set ) === null && is_string( $values[$field] ) && preg_match( static::LINK_VALUE_PATTERN, trim( $values[$field] ) ) ) ) );
        // the labels of links are only used for the links and not imported as text too
        $labels = $this->labels( $values, $links );

        foreach( $values as $field => $value )
        {
            if( !is_string( $field ) || empty( $value ) || in_array( $field, $labels, true ) || in_array( $field, static::SET_KEYS, true ) || $this->setting( $this->grids, $field, $set ) ) {
                continue;
            }

            $type = $this->setting( $this->texts, $field, $set )['type'] ?? null;

            if( in_array( $field, $links, true ) )
            {
                $label = isset( $labels[$field] ) ? (string) $values[$labels[$field]] : '';
                $contents[] = is_string( $value ) ? $this->linkContent( $value, $label, !$this->setting( $this->across, $field, $set ) ) : null;
            }
            elseif( is_array( $value ) && $this->setting( $this->lists, $field, $set ) )
            {
                $contents[] = $this->listContent( $value );
            }
            elseif( is_array( $value ) && ( $settings = $this->setting( $this->arrays, $field, $set ) ) !== null )
            {
                $contents[] = $this->arrayContent( $value, $settings );
            }
            elseif( is_string( $value ) && $this->setting( $this->videos, $field, $set ) )
            {
                $contents[] = $this->video( $value, $field, $set );
            }
            elseif( in_array( $field, static::ASSET_KEYS, true ) || $this->setting( $this->containers, $field, $set ) !== null )
            {
                foreach( (array) $value as $path )
                {
                    if( is_string( $path ) && preg_match( static::FILE_PATTERN, $path ) && ( $asset = self::asset( $path, $this->container( $field, $set ) ) ) !== '' ) {
                        $contents[] = preg_match( static::IMAGE_PATTERN, $path ) ? $this->image( $asset, $values ) : $this->media( $asset );
                    }
                }
            }
            elseif( is_string( $value ) && in_array( $field, static::HEADING_FIELDS, true ) && in_array( $type, [null, 'text'], true ) )
            {
                $title = trim( (string) preg_replace( '/\s+/', ' ', $value ) );
                $level = Bard::level( $values['level'] ?? $values['heading_level'] ?? 2 );
                $contents[] = $title !== '' ? Elements::content( Elements::create( 'heading', ['level' => $level, 'title' => $title] ) ) : null;
            }
            elseif( is_array( $value ) && $this->isTable( $value ) )
            {
                $contents[] = $this->table( $value, !empty( $values['first_row_headers'] ) );
            }
            elseif( is_array( $value ) || in_array( $type, static::CONTENT_TYPES, true ) || in_array( $field, static::TEXT_FIELDS, true ) )
            {
                $contents[] = $this->elements( $value, $field, $set );
            }
        }

        return Elements::merge( ...$contents );
    }


    /**
     * Returns a table element with the keys or their labels and the values of an Array field.
     *
     * Arrays with the "expand" option are stored as list of key/value pairs like [{"key": "a", "value": "1"}],
     * the headers of the key and value columns are the first row if they are set.
     *
     * @param array<mixed> $value Values of the Array field by key or as list of key/value pairs
     * @param ArrayField $settings Labels of the keys and column headers from the blueprint
     * @return ContentElements|null Table element or NULL if the array has no values
     */
    protected function arrayContent( array $value, array $settings ): ?array
    {
        $rows = [];

        foreach( $value as $key => $item )
        {
            if( is_array( $item ) && array_key_exists( 'key', $item ) ) {
                [$key, $item] = [$item['key'], $item['value'] ?? null];
            }

            if( is_scalar( $key ) && is_scalar( $item ) && ( $item = trim( (string) $item ) ) !== '' ) {
                $rows[] = [$this->plain( $settings['labels'][(string) $key] ?? (string) $key ), $this->plain( $item )];
            }
        }

        if( !$rows ) {
            return null;
        }

        $headers = array_map( fn( $header ) => $this->plain( $header ), $settings['headers'] );
        $header = array_filter( $headers, fn( $cell ) => $cell !== '' ) !== [];

        return Elements::content( Elements::create( 'table', [
            'title' => '',
            'header' => $header ? 'row+col' : 'col',
            'table' => $header ? [$headers, ...$rows] : $rows,
        ] ) );
    }


    /**
     * Returns the label fields of the Link fields of a set or group.
     *
     * Each Link field uses the field named like itself or without "_link" or "_url" followed by "_label" or
     * "_text", e.g. "primary_label" for "primary_link". If the set has a single Link field, the first field of
     * the common label fields like "label" or "button_text" is used otherwise.
     *
     * @param array<mixed> $values Fields of the set or group by handle
     * @param array<int, string> $links Handles of the Link fields
     * @return array<string, string> Label field handles by Link field handle
     */
    protected function labels( array $values, array $links ): array
    {
        $labels = [];

        foreach( $links as $link )
        {
            $base = (string) preg_replace( '/_(?:link|url)$/', '', $link );
            $fields = array_unique( [$link . '_label', $link . '_text', $base . '_label', $base . '_text'] );
            $fields = array_merge( $fields, count( $links ) === 1 ? static::LABEL_FIELDS : [] );

            foreach( $fields as $field )
            {
                if( is_string( $values[$field] ?? null ) ) {
                    $labels[$link] = $field;
                    break;
                }
            }
        }

        return $labels;
    }


    /**
     * Returns a text element with the items of a List field as bullet list.
     *
     * Statamic templates output the items as they are, so lists with HTML tags in their items are converted
     * as sanitized HTML like Text fields, other items are escaped.
     *
     * @param array<mixed> $value Items of the List field
     * @return ContentElements|null Text element or NULL if the list has no items
     */
    protected function listContent( array $value ): ?array
    {
        $items = array_filter( array_map( fn( $item ) => is_scalar( $item ) ? trim( (string) preg_replace( '/\s+/', ' ', (string) $item ) ) : '', $value ), fn( $item ) => $item !== '' );

        if( !$items ) {
            return null;
        }

        if( array_filter( $items, fn( $item ) => preg_match( static::TAG_PATTERN, $item ) === 1 ) ) {
            return $this->html()->elements( '<ul>' . implode( '', array_map( fn( $item ) => '<li>' . ( preg_match( static::TAG_PATTERN, $item ) ? $item : e( $item ) ) . '</li>', $items ) ) . '</ul>' );
        }

        return Elements::content( $this->textElement( implode( "\n", array_map( fn( $item ) => '- ' . $this->plain( $item ), $items ) ) ) );
    }


    /**
     * Returns a text element with the link of a Link field.
     *
     * Link fields contain URLs, entries like "entry::ID", assets like "asset::container::path" or "@child" for
     * the first child entry. Links without label show the URL, links whose targets can't be resolved are skipped
     * together with their labels because a label without link target isn't useful as button or link text.
     *
     * @param string $value Value of the Link field
     * @param string $label Label of the link
     * @param bool $localize If links to entries refer to the entries of the site of the converted entry
     * @return ContentElements|null Text element or NULL if the link target can't be resolved
     */
    protected function linkContent( string $value, string $label, bool $localize = true ): ?array
    {
        $value = trim( $value );
        $url = $value === '@child' ? ( $this->reference )( $value, $localize ) : $this->link( $value, $localize );

        if( $url === null || $url === '' )
        {
            if( $this->warn && $value !== '' ) {
                ( $this->warn )( "link:{$value}", "Link target \"{$value}\" can't be resolved, the link isn't imported" );
            }
            return null;
        }

        $label = trim( (string) preg_replace( '/\s+/', ' ', $label ) ) ?: $url;
        $text = $this->bard->markdown( ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => $label, 'marks' => [['type' => 'link', 'attrs' => ['href' => $url]]]],
        ]] );

        return Elements::content( $this->textElement( trim( $text ) ) );
    }


    /**
     * Returns an image element for the asset with the alt text and caption of the set or group.
     *
     * @param string $asset Asset like "container::path", absolute path or URL
     * @param array<mixed> $values Fields of the set or group which may contain "alt" and "caption" fields
     * @return ContentElements|null Image element or NULL if the image couldn't be imported
     */
    protected function image( string $asset, array $values ): ?array
    {
        $caption = is_string( $values['caption'] ?? null ) ? trim( $values['caption'] ) : '';
        $alt = is_string( $values['alt'] ?? null ) && trim( $values['alt'] ) !== '' ? trim( $values['alt'] ) : $caption;

        return Elements::imageContent( ( $this->asset )( $asset, $alt ), $caption );
    }


    /**
     * Returns a video, audio or file element for the asset depending on its file extension.
     *
     * @param string $asset Asset like "container::path", absolute path or URL
     * @return ContentElements|null Video, audio or file element or NULL if the file couldn't be imported
     */
    protected function media( string $asset ): ?array
    {
        if( !( $fileId = ( $this->asset )( $asset ) ) ) {
            return null;
        }

        $path = (string) ( parse_url( $asset, PHP_URL_PATH ) ?: $asset );
        $type = match( true ) {
            (bool) preg_match( static::VIDEO_PATTERN, $path ) => 'video',
            (bool) preg_match( static::AUDIO_PATTERN, $path ) => 'audio',
            default => 'file',
        };

        return Elements::fileContent( $type, $fileId );
    }


    /**
     * Converts the fields of a Group field into Pagible content elements.
     *
     * @param array<mixed> $values Fields of the group by handle
     * @param string|null $field Field handle
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return ContentElements
     */
    protected function group( array $values, ?string $field, ?string $set ): array
    {
        $content = $this->fields( $values, $set );

        if( !$content['elements'] && $this->warn && array_filter( $values ) ) {
            ( $this->warn )( 'group:' . ( $field ?? '' ), 'Group field' . ( $field !== null ? " \"{$field}\"" : '' ) . ' contains no content which can be imported' );
        }

        return $content;
    }


    /**
     * Returns the HTML converter for HTML content.
     */
    protected function html(): Html
    {
        return $this->html ??= new Html( function( string $tag ) {
            if( !( $source = Html::decodedSource( $tag ) ) || ( $src = self::asset( $source['src'], $this->container ) ) === '' ) {
                return null;
            }

            return ( $this->asset )( $src, $source['alt'] );
        }, $this->warn ? fn( string $msg ) => ( $this->warn )( $msg, $msg ) : null );
    }


    /**
     * Tests if the value looks like Bard ProseMirror content.
     *
     * Replicator sets can be named like Bard nodes, e.g. "image", but have other fields than Bard nodes.
     *
     * @param array<mixed> $value Field value
     */
    protected function isBard( array $value ): bool
    {
        if( ( $value['type'] ?? null ) === 'doc' ) {
            return true;
        }

        $first = $value[0] ?? null;

        return array_is_list( $value ) && is_array( $first ) && in_array( $first['type'] ?? null, static::BARD_TYPES, true )
            && array_diff( array_keys( $first ), static::BARD_KEYS ) === [] || $this->isInlineBard( $value );
    }


    /**
     * Tests if the value looks like the value of a Code field with selectable language like {"code": "...", "mode": "php"}.
     *
     * @param array<mixed> $value Field value
     */
    protected function isCode( array $value ): bool
    {
        return is_string( $value['code'] ?? null ) && array_diff( array_keys( $value ), ['code', 'mode'] ) === [];
    }


    /**
     * Tests if the value looks like the rows of a Table field like [{"cells": ["A", "B"]}].
     *
     * @param array<mixed> $value Field value
     */
    protected function isTable( array $value ): bool
    {
        if( $value === [] || !array_is_list( $value ) ) {
            return false;
        }

        foreach( $value as $row )
        {
            if( !is_array( $row ) || array_keys( $row ) !== ['cells'] || !is_array( $row['cells'] ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the value looks like the rows of a Grid field, a list of fields without type.
     *
     * @param array<mixed> $value Field value
     */
    protected function isGrid( array $value ): bool
    {
        if( $value === [] || !array_is_list( $value ) ) {
            return false;
        }

        foreach( $value as $row )
        {
            if( !is_array( $row ) || $row === [] || array_is_list( $row ) || isset( $row['type'] ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the value looks like inline Bard content, i.e. text nodes without paragraphs.
     *
     * Bard fields with the "inline" option store their text nodes and line breaks only.
     *
     * @param array<mixed> $value Field value
     */
    protected function isInlineBard( array $value ): bool
    {
        if( $value === [] || !array_is_list( $value ) ) {
            return false;
        }

        foreach( $value as $node )
        {
            if( !is_array( $node ) || !in_array( $node['type'] ?? null, static::INLINE_TYPES, true ) || array_diff( array_keys( $node ), static::BARD_KEYS ) !== []
                || ( $node['type'] === 'text' && !is_string( $node['text'] ?? null ) ) )
            {
                return false;
            }
        }

        return true;
    }


    /**
     * Tests if the value looks like Replicator content, a list of sets with their type.
     *
     * @param array<mixed> $value Field value
     */
    protected function isReplicator( array $value ): bool
    {
        if( $value === [] || !array_is_list( $value ) ) {
            return false;
        }

        foreach( $value as $set )
        {
            if( !is_array( $set ) || !is_string( $set['type'] ?? null ) ) {
                return false;
            }
        }

        return true;
    }


    /**
     * Converts a Markdown or HTML string into Pagible content elements.
     *
     * HTML starting with a block tag and Markdown with inline HTML are converted and sanitized by the HTML converter.
     * The text of Text and Textarea fields is plain text or HTML, Bard fields saving HTML contain HTML and Markdown
     * fields use their settings from the blueprint. Markdown using footnotes, attributes or description lists
     * which Pagible doesn't support, Markdown fields with smart punctuation and Markdown with images, whose
     * assets are imported as files by the HTML converter, are converted to HTML like Statamic.
     *
     * @param string $text Markdown, HTML or plain text
     * @param string|null $field Field handle to get the type of the field from the blueprint
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return ContentElements
     */
    protected function markdownOrHtml( string $text, ?string $field = null, ?string $set = null ): array
    {
        if( ( $text = trim( $text ) ) === '' ) {
            return Elements::content();
        }

        // links to entries and assets of Bard fields saving HTML and of Markdown fields are resolved
        if( str_contains( $text, 'statamic://' ) ) {
            $text = $this->references( $text );
        }

        $settings = $field !== null ? $this->setting( $this->texts, $field, $set ) : null;
        $type = $settings['type'] ?? null;
        $escape = $settings['escape'] ?? false;

        if( $type === 'bard' || $type !== 'markdown' && preg_match( static::BLOCK_HTML_PATTERN, $text ) ) {
            return $this->html()->elements( $text );
        }

        $html = !$escape && preg_match( static::TAG_PATTERN, $text );

        // Text and Textarea fields aren't Markdown, so Markdown characters in their text are escaped
        if( in_array( $type, ['text', 'textarea'], true ) ) {
            return $html ? $this->html()->elements( $text ) : Elements::content( Elements::create( 'text', ['text' => $this->plain( $text )] ) );
        }

        if( ( $settings['smart'] ?? false ) || preg_match( static::EXTENDED_PATTERN, $text ) || preg_match( static::IMAGE_MD_PATTERN, $text ) ) {
            return $this->html()->elements( $this->render( $text, $settings ) );
        }

        $text = $this->markdown( $text, $settings['breaks'] ?? true, $settings['links'] ?? false, $escape );

        // Markdown with inline HTML is converted to HTML first so the HTML converter sanitizes it
        if( $html ) {
            return $this->html()->elements( Str::markdown( $text ) );
        }

        return Elements::content( Elements::create( 'text', ['text' => $text] ) );
    }


    /**
     * Applies the line break, link and HTML settings of Statamic Markdown fields to the Markdown text.
     *
     * Statamic converts single line breaks into line breaks (automatic_line_breaks) and doesn't link URLs
     * (automatic_links) by default while Pagible renders single line breaks as spaces and links URLs. Code
     * blocks, HTML blocks, code spans, HTML tags and autolinks like <https://...> are left as they are. HTML
     * of fields escaping markup (escape_markup) is escaped and shown as text.
     *
     * @param string $text Markdown text
     * @param bool $breaks If single line breaks become line breaks
     * @param bool $links If URLs and e-mail addresses are linked automatically
     * @param bool $escape If HTML is shown as text
     * @return string Markdown text
     */
    protected function markdown( string $text, bool $breaks, bool $links, bool $escape = false ): string
    {
        $lines = explode( "\n", str_replace( ["\r\n", "\r"], "\n", $text ) );
        $fence = $end = null;
        $code = $list = false;
        $count = count( $lines );

        foreach( $lines as $idx => $line )
        {
            $blank = trim( $line ) === '';
            $start = $idx === 0 || trim( $lines[$idx - 1] ) === '';

            if( $fence !== null )
            {
                if( preg_match( '/^ {0,3}(`{3,}|~{3,})\s*$/', $line, $match ) && $match[1][0] === $fence[0] && strlen( $match[1] ) >= strlen( $fence ) ) {
                    $fence = null;
                }
                continue;
            }

            // HTML blocks end at their end condition or at a blank line
            if( $end !== null )
            {
                if( $end === '' ? $blank : preg_match( $end, $line ) ) {
                    $end = null;
                }
                continue;
            }

            if( preg_match( '/^ {0,3}(`{3,}|~{3,})/', $line, $match ) ) {
                $fence = $match[1];
                continue;
            }

            // indented code blocks follow a blank line or another code line but not a list item
            $code = !$blank && preg_match( '/^(?: {4}|\t)/', $line ) && !$list && ( $code || $start );

            if( $blank || $code ) {
                continue;
            }

            if( !$escape && ( $end = $this->htmlBlock( $line, $start ) ) !== null )
            {
                if( $end !== '' && preg_match( $end, $line ) ) {
                    $end = null;
                }
                continue;
            }

            if( preg_match( '/^\S/', $line ) ) {
                $list = (bool) preg_match( '/^(?:[-+*]|\d+[.)])\s/', $line );
            } elseif( preg_match( '/^\s+(?:[-+*]|\d+[.)])\s/', $line ) ) {
                $list = true;
            }

            if( !$links || $escape )
            {
                // code spans, HTML tags and autolinks aren't escaped, HTML tags are escaped if markup is escaped
                preg_match_all( '/(`+).*?(?<!`)\\1(?!`)|<[^>\\n]*>/', $line, $matches, PREG_OFFSET_CAPTURE );
                $md = '';
                $pos = 0;

                foreach( $matches[0] as [$match, $offset] )
                {
                    $md .= $this->unlinked( substr( $line, $pos, $offset - $pos ), $links ) . ( $escape && preg_match( '/^<\/?[a-z][a-z0-9-]*(?:[\s\/][^>]*)?>$/i', $match ) ? '\\' . $match : $match );
                    $pos = $offset + strlen( $match );
                }

                $line = $md . $this->unlinked( substr( $line, $pos ), $links );
            }

            if( $breaks && $idx + 1 < $count && trim( $lines[$idx + 1] ) !== '' && !preg_match( '/(?: {2}|\\\\)$/', $line ) ) {
                $line .= '  ';
            }

            $lines[$idx] = $line;
        }

        return implode( "\n", $lines );
    }


    /**
     * Returns the end condition of the HTML block starting with the line.
     *
     * @param string $line Markdown line
     * @param bool $start If the line doesn't continue a paragraph
     * @return string|null Regular expression matching the end of the block, empty if it ends at a blank line or NULL if the line doesn't start an HTML block
     */
    protected function htmlBlock( string $line, bool $start ): ?string
    {
        foreach( static::HTML_BLOCKS as $pattern => $end )
        {
            if( preg_match( $pattern, $line ) ) {
                return $end;
            }
        }

        // lines with a single tag can't interrupt a paragraph
        return $start && preg_match( static::HTML_LINE_PATTERN, $line ) ? '' : null;
    }


    /**
     * Returns the text converted from plain text to Markdown with escaped Markdown characters.
     *
     * @param string $text Plain text
     * @return string Markdown text
     */
    protected function plain( string $text ): string
    {
        return trim( $this->bard->markdown( ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => trim( $text )]]] ) );
    }


    /**
     * Returns the Markdown text of a text field for quotes and callouts, which can't contain blocks.
     *
     * @param string $text Text of the field
     * @param TextField|null $settings Type and Markdown settings of the field or NULL if unknown
     * @return string Markdown text
     */
    protected function prepare( string $text, ?array $settings ): string
    {
        if( in_array( $settings['type'] ?? null, ['text', 'textarea'], true ) ) {
            return $this->plain( $text );
        }

        return $this->markdown( trim( $text ), $settings['breaks'] ?? true, $settings['links'] ?? false, $settings['escape'] ?? false );
    }


    /**
     * Converts Markdown to HTML with the CommonMark extensions Statamic uses.
     *
     * @param string $text Markdown text
     * @param TextField|null $settings Type and Markdown settings of the field or NULL if unknown
     * @return string HTML which must be sanitized
     */
    protected function render( string $text, ?array $settings ): string
    {
        $config = ['html_input' => ( $settings['escape'] ?? false ) ? 'escape' : 'allow', 'allow_unsafe_links' => false];

        if( $settings['breaks'] ?? true ) {
            $config['renderer'] = ['soft_break' => "<br />\n"];
        }

        $env = new Environment( $config );
        $env->addExtension( new Extension\CommonMark\CommonMarkCoreExtension() );
        $env->addExtension( new Extension\Table\TableExtension() );
        $env->addExtension( new Extension\Attributes\AttributesExtension() );
        $env->addExtension( new Extension\Strikethrough\StrikethroughExtension() );
        $env->addExtension( new Extension\DescriptionList\DescriptionListExtension() );
        $env->addExtension( new Extension\Footnote\FootnoteExtension() );
        $env->addExtension( new Extension\TaskList\TaskListExtension() );

        if( $settings['links'] ?? false ) {
            $env->addExtension( new Extension\Autolink\AutolinkExtension() );
        }

        if( $settings['smart'] ?? false ) {
            $env->addExtension( new Extension\SmartPunct\SmartPunctExtension() );
        }

        return (string) ( new MarkdownConverter( $env ) )->convert( $text );
    }


    /**
     * Returns the settings of the field in the set or of the field by its handle.
     *
     * @template T
     * @param array<string, T> $map Settings by field key like "set.field" or "field"
     * @param string $field Field handle
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return T|null Settings or NULL if unknown
     */
    protected function setting( array $map, string $field, ?string $set ): mixed
    {
        return ( $set !== null ? $map[$set . '.' . $field] ?? null : null ) ?? $map[$field] ?? null;
    }


    /**
     * Returns the text with escaped URLs and e-mail addresses unless they are linked automatically.
     *
     * @param string $text Markdown text
     * @param bool $links If URLs and e-mail addresses are linked automatically
     */
    protected function unlinked( string $text, bool $links ): string
    {
        return $links ? $text : Bard::unlink( $text );
    }


    /**
     * Replaces the links to Statamic entries and assets like "statamic://entry::ID" in HTML and Markdown text.
     *
     * Links whose targets can't be resolved are replaced by their text.
     *
     * @param string $text HTML or Markdown text
     * @return string Text with resolved links
     */
    protected function references( string $text ): string
    {
        $text = (string) preg_replace_callback( '/<a\b([^>]*?)\bhref\s*=\s*(["\'])\s*(statamic:\/\/[^"\']*)\2([^>]*)>(.*?)<\/a>/is', function( $match ) {
            $url = $this->link( html_entity_decode( $match[3], ENT_QUOTES | ENT_HTML5 ) );
            return $url !== null ? '<a' . $match[1] . 'href="' . e( $url ) . '"' . $match[4] . '>' . $match[5] . '</a>' : $match[5];
        }, $text );

        return (string) preg_replace_callback( '/(!?)\[([^\]]*)\]\(\s*<?(statamic:\/\/[^\s)>]+)>?((?:\s+"[^"]*")?)\s*\)/', function( $match ) {
            // images of assets are imported by the HTML converter with the meta data of the asset
            if( $match[1] === '!' && str_starts_with( $match[3], 'statamic://asset::' ) ) {
                return $match[0];
            }

            $url = $this->link( $match[3] );
            return $url !== null ? $match[1] . '[' . $match[2] . '](<' . str_replace( ['<', '>', ' '], ['%3C', '%3E', '%20'], $url ) . '>' . $match[4] . ')' : $match[2];
        }, $text );
    }


    /**
     * Converts a Replicator set into Pagible content elements.
     *
     * @param array<mixed> $set Replicator set with its type and fields
     * @return ContentElements|null
     */
    protected function replicatorSet( array $set ): ?array
    {
        // disabled sets are hidden by Statamic
        return ( $set['enabled'] ?? true ) !== false ? $this->set( $set ) : null;
    }


    /**
     * Converts a Bard or Replicator set by its type into Pagible content elements.
     *
     * @param array<mixed> $values Fields of the set including its type
     * @return ContentElements|null
     */
    protected function set( array $values ): ?array
    {
        $type = (string) ( $values['type'] ?? '' );

        foreach( $values as $field => $value )
        {
            if( $this->warn && is_string( $field ) && !empty( $value ) && $this->setting( $this->grids, $field, $type ) ) {
                ( $this->warn )( "grid:{$type}.{$field}", "Grid field \"{$field}\" of \"{$type}\" sets isn't imported" );
            }
        }

        return match( (string) ( $values['type'] ?? '' ) ) {
            'image', 'image_block', 'photo' => $this->setImage( $values ),
            'code', 'code_block' => $this->setCode( $values ),
            'quote', 'blockquote', 'pullquote' => $this->setQuote( $values ),
            'video', 'video_block' => $this->setVideo( $values ),
            'gallery', 'image_gallery', 'slideshow' => $this->setGallery( $values ),
            'callout', 'notice', 'alert' => $this->setCallout( $values ),
            'heading', 'header' => $this->setHeading( $values ),
            // text sets and unknown sets are converted from their text content if they have one
            default => $this->setText( $values ),
        };
    }


    /**
     * Converts a callout set into a text element with the kind of callout as label.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setCallout( array $values ): ?array
    {
        $field = isset( $values['callout_text'] ) ? 'callout_text' : ( isset( $values['text'] ) ? 'text' : 'body' );
        $text = $values[$field] ?? '';

        // plain text is escaped and Markdown uses the settings of the field
        if( is_string( $text ) && trim( $text ) !== '' && !Bard::hasHtml( $text ) ) {
            $text = $this->prepare( $text, $this->setting( $this->texts, $field, (string) ( $values['type'] ?? '' ) ) );
        }

        $text = is_array( $text ) ? trim( implode( '', array_map( fn( $node ) => is_array( $node ) ? $this->bard->markdown( $node ) : '', $this->unwrap( $text ) ) ) ) : $text;

        if( !is_string( $text ) || trim( $text ) === '' ) {
            return null;
        }

        // the type of the set is its handle, the kind of callout is stored in another field
        $label = array_filter( [$values['callout_type'] ?? null, $values['style'] ?? null, $values['variant'] ?? null], fn( $value ) => is_string( $value ) && $value !== '' );
        $label = ucfirst( (string) ( reset( $label ) ?: 'Note' ) );

        return Elements::content( $this->textElement( "**{$label}:** " . trim( $text ) ) );
    }


    /**
     * Converts a code set into a code element.
     *
     * The code field stores the code and its language like {"code": "...", "mode": "php"}.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setCode( array $values ): ?array
    {
        $code = $values['code'] ?? $values['text'] ?? $values['snippet'] ?? '';
        $lang = $values['language'] ?? $values['lang'] ?? '';

        if( is_array( $code ) ) {
            $lang = $code['mode'] ?? $lang;
            $code = $code['code'] ?? '';
        }

        if( !is_string( $code ) || trim( $code ) === '' ) {
            return null;
        }

        return Elements::content( Elements::create( 'code', ['language' => $this->language( is_string( $lang ) ? $lang : '' ), 'text' => $code] ) );
    }


    /**
     * Converts a gallery set into image elements.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setGallery( array $values ): ?array
    {
        $field = $this->field( $values, ['images', 'photos', 'gallery'] );
        $contents = [];

        foreach( $field !== null ? (array) $values[$field] : [] as $path )
        {
            if( is_string( $path ) && ( $asset = self::asset( $path, $this->container( $field, (string) ( $values['type'] ?? '' ) ) ) ) !== '' ) {
                $contents[] = Elements::imageContent( ( $this->asset )( $asset ) );
            }
        }

        return ( $content = Elements::merge( ...$contents ) )['elements'] ? $content : null;
    }


    /**
     * Converts a heading set into a heading element.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setHeading( array $values ): ?array
    {
        $title = $values['heading'] ?? $values['title'] ?? $values['text'] ?? '';

        if( !is_string( $title ) || trim( $title ) === '' ) {
            return null;
        }

        return Elements::content( Elements::create( 'heading', ['level' => Bard::level( $values['level'] ?? $values['heading_level'] ?? 2 ), 'title' => trim( (string) preg_replace( '/\s+/', ' ', $title ) )] ) );
    }


    /**
     * Converts an image set into a Pagible image or image-text element.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setImage( array $values ): ?array
    {
        $field = $this->field( $values, ['image', 'photo', 'file'] );
        $path = $field !== null ? $values[$field] : '';
        $path = is_array( $path ) ? reset( $path ) : $path;

        if( !is_string( $path ) || ( $asset = self::asset( $path, $this->container( $field, (string) ( $values['type'] ?? '' ) ) ) ) === '' ) {
            return null;
        }

        return $this->image( $asset, $values );
    }


    /**
     * Converts a quote set into a text element.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setQuote( array $values ): ?array
    {
        $field = $this->field( $values, ['quote', 'quote_text', 'text'] );
        $quote = $field !== null ? $values[$field] : '';

        if( !is_string( $quote ) || trim( $quote ) === '' ) {
            return null;
        }

        // plain text is escaped and Markdown uses the settings of the field
        $quote = $this->prepare( $quote, $this->setting( $this->texts, $field, (string) ( $values['type'] ?? '' ) ) );
        $cite = $values['cite'] ?? $values['attribution'] ?? $values['source'] ?? $values['quote_source'] ?? '';
        $md = implode( "\n", array_map( fn( $line ) => rtrim( '> ' . $line ), explode( "\n", $quote ) ) );

        if( is_string( $cite ) && trim( $cite ) !== '' ) {
            $md .= "\n> -- *" . $this->plain( $cite ) . '*';
        }

        return Elements::content( Elements::create( 'text', ['text' => $md] ) );
    }


    /**
     * Converts a text set or a set of unknown type into Pagible elements from its fields in their order.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setText( array $values ): ?array
    {
        $type = (string) ( $values['type'] ?? '' );

        if( ( $content = $this->fields( $values, $type ) )['elements'] ) {
            return $content;
        }

        if( $this->warn && array_filter( array_diff_key( $values, array_flip( static::SET_KEYS ) ) ) ) {
            ( $this->warn )( "set:{$type}", "Sets of type \"{$type}\" contain no content which can be imported" );
        }

        return null;
    }


    /**
     * Converts a video set into a video player, a video element or a link to the video.
     *
     * The Video field of the set in the blueprint is used or else the first field named like a video field.
     *
     * @param array<mixed> $values Fields of the set
     * @return ContentElements|null
     */
    protected function setVideo( array $values ): ?array
    {
        $set = (string) ( $values['type'] ?? '' );
        $videos = array_filter( array_keys( $values ), fn( $field ) => is_string( $field ) && $this->setting( $this->videos, $field, $set ) );
        $field = reset( $videos ) ?: $this->field( $values, ['url', 'video_url', 'src', 'video', 'file'] );
        $url = $field !== null ? $values[$field] : '';
        $url = is_array( $url ) ? reset( $url ) : $url;

        return is_string( $url ) ? $this->video( $url, $field, $set ) : null;
    }


    /**
     * Converts the URL of a video into an html element with the player of a YouTube, Vimeo or Cloudflare Stream
     * video, a video element for video files and assets or a link to other videos.
     *
     * Other pages aren't embedded because the iframe isn't sanitized and would show any page of the editor's choice.
     *
     * @param string $url URL of the video or path of the video asset
     * @param string|null $field Handle of the field containing the video
     * @param string|null $set Handle of the Replicator or Bard set containing the field
     * @return ContentElements|null
     */
    protected function video( string $url, ?string $field, ?string $set ): ?array
    {
        if( ( $url = trim( $url ) ) === '' ) {
            return null;
        }

        if( $content = Embeds::iframeContent( Embeds::player( $url ) ) ) {
            return $content;
        }

        // Video fields contain URLs of video files while asset fields contain paths
        if( preg_match( static::VIDEO_PATTERN, (string) ( parse_url( $url, PHP_URL_PATH ) ?: $url ) )
            && ( preg_match( '#^https?://#i', $url ) ? Utils::isValidUrl( $url, strict: false ) : !preg_match( '#^[a-z][a-z0-9+.-]*:(?!:)|^//#i', $url ) )
            && ( $asset = self::asset( $url, $this->container( $field, $set ) ) ) !== ''
            && ( $content = $this->media( $asset ) ) )
        {
            return $content;
        }

        if( !preg_match( '#^https?://#i', $url ) || !Utils::isValidUrl( $url, strict: false ) ) {
            return null;
        }

        return Elements::content( Elements::create( 'html', ['text' => Utils::html( '<p><a href="' . e( $url ) . '">' . e( $url ) . '</a></p>' )] ) );
    }


    /**
     * Converts the rows of a Table field into a table element.
     *
     * The cells contain inline Markdown like Statamic renders them with the "markdown" modifier, URLs aren't linked.
     *
     * @param array<int, array{cells: array<mixed>}> $rows Rows of the Table field
     * @param bool $header TRUE if the first row contains the column headers, set by a "first_row_headers" field
     * @return ContentElements
     */
    protected function table( array $rows, bool $header = false ): array
    {
        $table = array_map( fn( $row ) => array_map(
            fn( $cell ) => is_scalar( $cell ) ? trim( $this->markdown( trim( (string) preg_replace( '/\s+/', ' ', (string) $cell ) ), false, false ) ) : '',
            array_values( $row['cells'] )
        ), $rows );

        if( array_filter( array_merge( ...$table ), fn( $cell ) => $cell !== '' ) === [] ) {
            return Elements::content();
        }

        return Elements::content( Elements::create( 'table', ['title' => '', 'header' => $header && count( $table ) > 1 ? 'row' : '', 'table' => $table] ) );
    }


    /**
     * Returns a text element for the Markdown text or an HTML element if it contains superscript or subscript text.
     *
     * @param string $markdown Markdown text converted from Bard nodes
     * @return array<string, mixed> Content element
     */
    protected function textElement( string $markdown ): array
    {
        if( Bard::hasHtml( $markdown ) ) {
            return Elements::create( 'html', ['text' => trim( Utils::html( Str::markdown( $markdown ) ) )] );
        }

        return Elements::create( 'text', ['text' => $markdown] );
    }


    /**
     * Returns the content of a Bard doc node or the Bard nodes, inline Bard content wrapped in a paragraph.
     *
     * @param array<mixed> $value Bard doc node or list of Bard nodes
     * @return array<mixed> Bard nodes
     */
    protected function unwrap( array $value ): array
    {
        if( $this->isInlineBard( $value ) ) {
            return [['type' => 'paragraph', 'content' => $value]];
        }

        return ( $value['type'] ?? null ) === 'doc' ? (array) ( $value['content'] ?? [] ) : $value;
    }
}
