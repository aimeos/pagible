<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;

use Aimeos\Cms\Import\Date;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads the published, scheduled and private posts, image attachments and featured images from a WordPress database.
 *
 * @phpstan-type WpPost object{
 *   ID: int,
 *   post_name: string,
 *   post_title: string,
 *   post_excerpt: string,
 *   post_content: string,
 *   post_date_gmt: string|null,
 *   post_date: string,
 *   post_modified_gmt: string|null,
 *   post_password: string,
 *   post_status: string,
 * }
 */
class Reader
{
    /** @var int Number of rows fetched by one query */
    protected const CHUNK_SIZE = 100;
    /** @var array<int, string> Columns of the posts which are used */
    protected const POST_COLUMNS = ['ID', 'post_name', 'post_title', 'post_excerpt', 'post_content', 'post_date_gmt', 'post_date', 'post_modified_gmt', 'post_password', 'post_status'];
    /** @var array<int, string> Columns of the posts which contain the texts */
    protected const TEXT_COLUMNS = ['post_excerpt', 'post_content'];
    /** @var int Number of attachment IDs and URLs fetched by one query */
    protected const BASENAME_CHUNK_SIZE = 5000;
    /** @var array<int, string> Status of the imported posts, scheduled and private posts are imported as disabled pages */
    public const STATUSES = ['publish', 'future', 'private'];
    /** @var int Maximum depth of reusable blocks used inside of other reusable blocks */
    protected const REUSABLE_DEPTH = 10;

    /** @var string Blocks whose attributes can be bound to custom fields or overridden in synced patterns, except list items */
    protected const BOUND_BLOCKS = '/(<!--\s*wp:(?:core\/)?(paragraph|heading|button|image)\s+(\{(?:(?!-->).)*?\})\s*-->)(.*?)(<!--\s*\/wp:(?:core\/)?\2\s*-->)/s';

    /** @var string List items with attributes followed by their own text up to a nested list or the end of the item */
    protected const BOUND_ITEMS = '/(<!--\s*wp:(?:core\/)?list-item\s+(\{(?:(?!-->).)*?\})\s*-->\s*<li\b[^>]*>)(?:(?!<!--\s*\/?wp:|<\/li>|<[uo]l\b).)*/s';

    /** @var string Attributes of all blocks which can be bound to custom fields */
    protected const BOUND_ATTRS = '/<!--\s*wp:(?:core\/)?(?:paragraph|heading|button|image|list-item)\s+(\{(?:(?!-->).)*?\})\s*-->/s';

    /** @var array<string, list<string>> Attributes of the blocks supported by block bindings like in WordPress */
    protected const BINDABLE = [
        'paragraph' => ['content'],
        'heading' => ['content'],
        'list-item' => ['content'],
        'image' => ['id', 'url', 'title', 'alt', 'caption'],
        'button' => ['url', 'text', 'linkTarget', 'rel'],
    ];

    /** @var array<int, string> Post meta keys of Yoast SEO and Rank Math with the SEO settings of the posts */
    public const SEO_KEYS = [
        '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_canonical', '_yoast_wpseo_meta-robots-noindex', '_yoast_wpseo_meta-robots-nofollow',
        '_yoast_wpseo_opengraph-title', '_yoast_wpseo_opengraph-description', '_yoast_wpseo_focuskw',
        'rank_math_title', 'rank_math_description', 'rank_math_canonical_url', 'rank_math_robots', 'rank_math_facebook_title', 'rank_math_facebook_description',
        'rank_math_focus_keyword',
    ];
    /** @var array<int, string> Post meta keys of Yoast SEO and Rank Math with the primary category of the posts used by their permalinks */
    public const PRIMARY_KEYS = ['_yoast_wpseo_primary_category', 'rank_math_primary_category'];
    /** @var array<string, string> Patterns matching the active plugin files of Yoast SEO and Rank Math by plugin */
    public const SEO_PLUGINS = ['yoast' => '#^wordpress-seo(?:-premium)?/#', 'rankMath' => '#^seo-by-rank-math(?:-pro)?/#'];

    /** @var string|null|false Base URL of the uploads from the options, NULL if not available, FALSE if not fetched yet */
    protected string|null|false $uploads = false;
    /** @var int|false|null ID of the default category, FALSE if the taxonomy tables aren't available, NULL if not fetched yet */
    protected int|false|null $terms = null;
    /** @var array<int, string>|false|null Active plugins of the site and the network, FALSE if unknown and NULL if not fetched yet */
    protected array|false|null $plugins = null;

    /**
     * @param Connection $db Connection to the WordPress database
     * @param string $prefix Prefix of the WordPress tables, the $table_prefix of wp-config.php
     * @throws \InvalidArgumentException If the prefix contains other characters than letters, digits and underscores
     */
    public function __construct( protected Connection $db, protected string $prefix = 'wp_' )
    {
        // WordPress itself refuses to run with other characters in $table_prefix
        if( preg_match( '/[^a-z0-9_]/i', $prefix ) ) {
            throw new \InvalidArgumentException( "Invalid WordPress table prefix: {$prefix}" );
        }
    }


    /**
     * Fetches the WordPress image attachments with the given IDs.
     *
     * The URL of the file is built from the "_wp_attached_file" post meta like wp_get_attachment_url() does,
     * because the guid is the URL at the time of the upload and isn't changed if the file is replaced.
     *
     * @param array<int|string> $ids IDs of the attachments, e.g. of the images used by one chunk of posts
     * @return array<int|string, array{guid: string, title: string, mime: string, url: string, alt: string}> Attachments by ID
     */
    public function attachments( array $ids ): array
    {
        $attachments = [];
        $table = $this->prefix . 'posts';

        foreach( array_chunk( array_values( array_unique( $ids ) ), \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            // the alternative text is shown by WordPress for featured images and the images of gallery shortcodes
            $query = $this->images()->whereIn( $table . '.ID', $chunk )->leftJoin( $this->prefix . 'postmeta as attached', fn( $join ) => $join
                ->on( 'attached.post_id', '=', $table . '.ID' )->where( 'attached.meta_key', '=', '_wp_attached_file' ) )
                ->leftJoin( $this->prefix . 'postmeta as alt', fn( $join ) => $join
                ->on( 'alt.post_id', '=', $table . '.ID' )->where( 'alt.meta_key', '=', '_wp_attachment_image_alt' ) );

            foreach( $query->get( [$table . '.ID', 'guid', 'post_title', 'post_mime_type', 'attached.meta_value as attached_file', 'alt.meta_value as alt'] ) as $attachment ) {
                $attachments[$this->id( $attachment )] = $this->attachment( $attachment );
            }
        }

        return $attachments;
    }


    /**
     * Fetches the IDs of the WordPress image attachments keyed by the basenames of their URLs.
     *
     * Attachments with the same basename are replaced by the one with the higher ID but keep the
     * position of the first one. Only the IDs are kept for all attachments, the other data is
     * fetched by attachments() for the used ones.
     *
     * The attachments are passed one by one, so they don't need to be kept in memory twice.
     *
     * @return \Generator<string, int|string> Attachment IDs by basename of the URL
     */
    public function basenames(): \Generator
    {
        foreach( $this->images()->select( 'ID', 'guid' )->lazyById( static::BASENAME_CHUNK_SIZE, 'ID' ) as $attachment ) {
            yield basename( $this->guid( $attachment ) ) => $this->id( $attachment );
        }
    }


    /**
     * Passes the rows of the published posts in chunks to the callback.
     *
     * The rows are validated by post() so invalid rows can be skipped. Each chunk continues after
     * the date and ID of the last post because skipping the previous rows gets slower with each chunk.
     *
     * If $texts is given, the texts are only fetched for the rows it returns TRUE for and are
     * empty for the other ones, so the texts of posts which aren't imported again aren't fetched.
     *
     * @param \Closure(Collection<int, \stdClass>): mixed $fn Callback receiving the post rows, FALSE stops
     * @param (\Closure(object): bool)|null $texts Returns TRUE for the post rows whose texts are required
     */
    public function chunk( \Closure $fn, ?\Closure $texts = null ): void
    {
        $last = null;
        $columns = $texts ? array_values( array_diff( self::POST_COLUMNS, self::TEXT_COLUMNS ) ) : self::POST_COLUMNS;

        do {
            $rows = $this->posts()->when( $last, fn( $query, $last ) => $query->where( fn( $query ) => $query
                ->where( 'post_date', '>', $last->post_date )
                ->orWhere( fn( $query ) => $query->where( 'post_date', $last->post_date )->where( 'ID', '>', $last->ID ) )
            ) )->limit( self::CHUNK_SIZE )->get( $columns );

            if( $texts ) {
                $this->texts( $rows->all(), $texts );
            }

            if( $rows->isEmpty() || $fn( $rows ) === false ) {
                return;
            }

            $last = $rows->last();
        } while( $rows->count() === self::CHUNK_SIZE );
    }


    /**
     * Fetches the footnotes of the posts which use the footnotes of the block editor (WordPress 6.3+).
     *
     * @param array<int|string, string> $contents Post contents by post ID, the footnotes are only fetched for posts with footnotes
     * @return array<int|string, array<string, string>> HTML texts of the footnotes by their ID by post ID
     */
    public function footnotes( array $contents ): array
    {
        $ids = array_keys( array_filter( $contents, fn( $content ) => str_contains( $content, 'data-fn' ) || preg_match( '/wp:(?:core\/)?footnotes\b/', $content ) ) );
        $result = [];

        if( $ids === [] ) {
            return [];
        }

        $rows = $this->db->table( $this->prefix . 'postmeta' )->where( 'meta_key', 'footnotes' )->whereIn( 'post_id', $ids )->pluck( 'meta_value', 'post_id' );

        foreach( $rows as $postId => $json )
        {
            foreach( is_string( $json ) && is_array( $list = json_decode( $json, true ) ) ? $list : [] as $note )
            {
                if( is_array( $note ) && is_string( $id = $note['id'] ?? null ) && is_string( $text = $note['content'] ?? null ) ) {
                    $result[$postId][$id] = $text;
                }
            }
        }

        return $result;
    }


    /**
     * Adds the IDs of the images attached to the post to gallery shortcodes without "ids" or "include" attribute.
     *
     * Like gallery_shortcode() of WordPress, these galleries show the images whose parent is the post or the
     * post in the "id" attribute. They are sorted by the "orderby" (default "menu_order ID") and "order"
     * attributes, images in the "exclude" attribute are left out. Random order is replaced by the default one.
     *
     * The images in the "ids" attribute are shown in their order unless the shortcode has an "orderby" attribute,
     * the ones in the "include" attribute of old galleries are sorted by "menu_order ID" by default. Sorted IDs
     * replace the ones of the shortcode, images which don't exist are left out like by WordPress.
     *
     * @param array<int|string, string> $contents Post contents by post ID
     * @return array<int|string, string> Post contents with the attachment IDs added to the gallery shortcodes by post ID
     */
    public function galleries( array $contents ): array
    {
        $parents = $included = [];

        foreach( $contents as $postId => $content )
        {
            foreach( $this->shortcodes( $content, 'gallery' ) as $attrs )
            {
                if( !( $ids = Shortcode::ids( $attrs ) ) ) {
                    $parents[] = $this->galleryParent( $attrs, $postId );
                } elseif( $this->gallerySorted( $attrs ) ) {
                    array_push( $included, ...$ids );
                }
            }
        }

        $parents = array_values( array_unique( array_filter( $parents ) ) );
        $included = array_values( array_unique( $included ) );

        if( !$parents && !$included ) {
            return $contents;
        }

        $children = $images = [];
        $columns = ['ID', 'post_parent', 'menu_order', 'post_title', 'post_name', 'post_date', 'post_modified'];

        foreach( array_chunk( $parents, \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            foreach( $this->images()->whereIn( 'post_parent', $chunk )->get( $columns ) as $row ) {
                $children[(int) get_object_vars( $row )['post_parent']][] = get_object_vars( $row );
            }
        }

        foreach( array_chunk( $included, \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            foreach( $this->images()->whereIn( 'ID', $chunk )->get( $columns ) as $row ) {
                $images[(int) get_object_vars( $row )['ID']] = get_object_vars( $row );
            }
        }

        foreach( $contents as $postId => $content )
        {
            if( !str_contains( $content, '[gallery' ) ) {
                continue;
            }

            $contents[$postId] = (string) preg_replace_callback( Shortcode::pattern(), function( $m ) use ( $children, $images, $postId ) {
                if( $m[2] !== 'gallery' || $m[1] === '[' ) {
                    return $m[0];
                }

                if( $ids = Shortcode::ids( $attrs = Shortcode::attributes( $m[3] ) ) )
                {
                    if( !$this->gallerySorted( $attrs ) ) {
                        return $m[0];
                    }

                    // the "exclude" attribute is only used for the attached images
                    $ids = $this->galleryIds( array_values( array_intersect_key( $images, array_flip( $ids ) ) ), array_diff_key( $attrs, ['exclude' => ''] ) );
                }
                else
                {
                    $ids = $this->galleryIds( $children[$this->galleryParent( $attrs, $postId )] ?? [], $attrs );
                }

                // the IDs are added after the other attributes, so an empty "ids" attribute doesn't replace them
                return $ids ? '[gallery' . $m[3] . ' ids="' . implode( ',', $ids ) . '"' . substr( $m[0], strlen( '[gallery' . $m[3] ) ) : $m[0];
            }, $content );
        }

        return $contents;
    }


    /**
     * Replaces the playlist shortcodes by audio or video shortcodes with the URLs of their files like wp_playlist_shortcode().
     *
     * Playlists show the audio files (or the video files if the "type" attribute is "video") in the "ids" or "include"
     * attribute or the ones attached to the post like galleries. Playlists without files are kept, so the converter can
     * report them.
     *
     * Video and audio shortcodes without URL get the URL of the first video or audio file attached to the post like
     * wp_video_shortcode() and wp_audio_shortcode(), the ones without attached file are kept too.
     *
     * @param array<int|string, string> $contents Post contents by post ID
     * @return array<int|string, string> Post contents with the playlists replaced by post ID
     */
    public function playlists( array $contents ): array
    {
        $ids = $parents = [];

        foreach( $contents as $postId => $content )
        {
            foreach( $this->shortcodes( $content, 'playlist' ) as $attrs )
            {
                if( $list = Shortcode::ids( $attrs ) ) {
                    array_push( $ids, ...$list );
                } else {
                    $parents[] = $this->galleryParent( $attrs, $postId );
                }
            }

            foreach( ['video', 'audio'] as $tag )
            {
                foreach( $this->shortcodes( $content, $tag ) as $attrs )
                {
                    if( Shortcode::source( $tag, $attrs ) === '' ) {
                        $parents[] = (int) $postId;
                    }
                }
            }
        }

        if( !$ids && !$parents ) {
            return $contents;
        }

        $files = $children = [];
        $table = $this->prefix . 'posts';
        $columns = [$table . '.ID', 'post_parent', 'menu_order', 'post_title', 'post_name', 'post_date', 'post_modified', 'guid', 'post_mime_type', 'attached.meta_value as attached_file'];

        foreach( [$table . '.ID' => $ids, 'post_parent' => $parents] as $column => $values )
        {
            foreach( array_chunk( array_values( array_unique( array_filter( $values ) ) ), \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
            {
                $query = $this->media()->whereIn( $column, $chunk )->leftJoin( $this->prefix . 'postmeta as attached', fn( $join ) => $join
                    ->on( 'attached.post_id', '=', $table . '.ID' )->where( 'attached.meta_key', '=', '_wp_attached_file' ) );

                foreach( $query->get( $columns ) as $row )
                {
                    $data = ['url' => $this->attachment( $row )['url']] + get_object_vars( $row );
                    $files[(int) $data['ID']] = $children[(int) $data['post_parent']][(int) $data['ID']] = $data;
                }
            }
        }

        foreach( $contents as $postId => $content )
        {
            if( !str_contains( $content, '[playlist' ) && !str_contains( $content, '[video' ) && !str_contains( $content, '[audio' ) ) {
                continue;
            }

            $contents[$postId] = (string) preg_replace_callback( Shortcode::pattern(), function( $m ) use ( $files, $children, $postId ) {
                if( !in_array( $m[2], ['playlist', 'video', 'audio'], true ) || $m[1] === '[' ) {
                    return $m[0];
                }

                $attrs = Shortcode::attributes( $m[3] );

                // video and audio shortcodes without URL show the first attached file in the order of get_attached_media()
                if( $m[2] !== 'playlist' )
                {
                    if( Shortcode::source( $m[2], $attrs ) !== '' ) {
                        return $m[0];
                    }

                    $list = array_filter( $children[(int) $postId] ?? [], fn( $file ) => str_starts_with( (string) $file['post_mime_type'], $m[2] . '/' ) );
                    $id = current( $this->galleryIds( $list, [] ) );

                    return $id !== false ? '[' . $m[2] . ' src="' . str_replace( ['"', '[', ']'], ['%22', '%5B', '%5D'], $files[$id]['url'] ) . '" attached="1"]' : $m[0];
                }
                $type = strtolower( trim( $attrs['type'] ?? '' ) ) === 'video' ? 'video' : 'audio';
                $list = ( $ids = Shortcode::ids( $attrs ) )
                    // the order of the IDs is used if there's no "orderby" attribute like for "post__in" in WordPress
                    ? array_filter( array_map( fn( $id ) => $files[$id] ?? null, $ids ) )
                    : $children[$this->galleryParent( $attrs, $postId )] ?? [];
                $list = array_filter( $list, fn( $file ) => str_starts_with( (string) $file['post_mime_type'], $type . '/' ) );

                if( !$ids || trim( $attrs['orderby'] ?? '' ) !== '' ) {
                    $list = array_map( fn( $id ) => $files[$id], $this->galleryIds( $list, $attrs ) );
                }

                // attached files are played whatever their type is, see Shortcode::playable()
                $tags = array_map( fn( $file ) => '[' . $type . ' src="' . str_replace( ['"', '[', ']'], ['%22', '%5B', '%5D'], $file['url'] ) . '" attached="1"]', $list );
                return $tags ? implode( "\n\n", $tags ) : $m[0];
            }, $content );
        }

        return $contents;
    }


    /**
     * Replaces the reusable blocks (synced patterns) in the post contents by their content.
     *
     * Reusable blocks are stored as posts of the "wp_block" type and referenced by "<!-- wp:block {"ref":123} /-->",
     * they can contain other reusable blocks. References to missing, unpublished and password protected blocks, which
     * WordPress doesn't show, are kept, so the converter can report them.
     * The texts, links and images overridden for the post in the "content" attribute of the reference are applied.
     *
     * @param array<int|string, string> $contents Post contents by post ID
     * @return array<int|string, string> Post contents with the reusable blocks replaced by post ID
     */
    public function reusable( array $contents ): array
    {
        $pattern = '/<!--\s*wp:(?:core\/)?block\s+(\{(?:(?!-->).)*?\})\s*\/-->/s';
        $blocks = [];

        for( $depth = 0; $depth < static::REUSABLE_DEPTH; $depth++ )
        {
            $refs = [];

            foreach( $contents as $content )
            {
                if( preg_match( '/wp:(?:core\/)?block\b/', $content ) && preg_match_all( $pattern, $content, $matches ) )
                {
                    foreach( $matches[1] as $json ) {
                        $refs[] = $this->ref( $json );
                    }
                }
            }

            if( ( $refs = array_diff( array_filter( array_unique( $refs ) ), array_keys( $blocks ) ) ) !== [] )
            {
                $blocks += $this->db->table( $this->prefix . 'posts' )->where( 'post_type', 'wp_block' )->where( 'post_status', 'publish' )
                    ->where( 'post_password', '' )->whereIn( 'ID', array_values( $refs ) )->pluck( 'post_content', 'ID' )->all() + array_fill_keys( $refs, null );
            }

            $changed = false;

            foreach( $contents as $postId => $content )
            {
                $contents[$postId] = (string) preg_replace_callback( $pattern, function( $m ) use ( $blocks, &$changed ) {
                    $block = $blocks[$this->ref( $m[1] )] ?? null;
                    $changed = $changed || is_string( $block );
                    return is_string( $block ) ? $this->overrides( $block, $m[1] ) : $m[0];
                }, $content );
            }

            if( !$changed ) {
                break;
            }
        }

        return $contents;
    }


    /**
     * Replaces the texts, links and images bound to custom fields by the values of the fields like WordPress 6.5+.
     *
     * Paragraph, heading, list item, button and image blocks including the image caption can be bound to custom fields of the post by "core/post-meta" block
     * bindings like "<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"core/post-meta","args":{"key":"price"}}}}} -->".
     * Like WordPress, protected fields starting with "_" aren't used. WordPress also requires the fields to be registered for
     * the REST API, which isn't stored in the database, so the saved content is only replaced by fields that aren't empty.
     *
     * @param array<int|string, string> $contents Post contents by post ID
     * @return array<int|string, string> Post contents with the bound values by post ID
     */
    public function bindings( array $contents ): array
    {
        $keys = $meta = [];

        foreach( $contents as $postId => $content )
        {
            if( str_contains( $content, 'core/post-meta' ) && preg_match_all( self::BOUND_ATTRS, $content, $matches ) )
            {
                foreach( $matches[1] as $json ) {
                    array_push( $keys, ...array_values( $this->metaBindings( $json ) ) );
                }
            }
        }

        if( ( $keys = array_values( array_unique( $keys ) ) ) === [] ) {
            return $contents;
        }

        $postIds = array_keys( array_filter( $contents, fn( $content ) => str_contains( $content, 'core/post-meta' ) ) );

        foreach( array_chunk( $postIds, \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            $rows = $this->db->table( $this->prefix . 'postmeta' )->whereIn( 'meta_key', $keys )->whereIn( 'post_id', $chunk )
                ->orderBy( 'meta_id' )->get( ['post_id', 'meta_key', 'meta_value'] );

            foreach( $rows as $row )
            {
                // like get_post_meta() with $single = true, the first value is used
                $data = get_object_vars( $row );
                $meta[(string) $data['post_id']][(string) $data['meta_key']] ??= $data['meta_value'];
            }
        }

        foreach( $postIds as $postId )
        {
            $contents[$postId] = $this->bound( $contents[$postId], function( string $type, string $json ) use ( $meta, $postId ) {
                $values = [];

                foreach( $this->metaBindings( $json ) as $attr => $key )
                {
                    // existing values are used even if they are empty, serialized arrays and objects aren't shown
                    if( is_string( $value = $meta[(string) $postId][$key] ?? null ) && !preg_match( '/^(?:a|O|C):\d+:/', $value ) ) {
                        $values[$attr] = $value;
                    }
                }

                return $values;
            } );
        }

        return $contents;
    }


    /**
     * Returns the keys of the custom fields bound to the block attributes by "core/post-meta" block bindings.
     *
     * @param string $json Attributes of the block
     * @return array<string, string> Custom field keys by block attribute name
     */
    protected function metaBindings( string $json ): array
    {
        $attrs = json_decode( $json, true );
        $bindings = is_array( $attrs ) && is_array( $attrs['metadata'] ?? null ) && is_array( $attrs['metadata']['bindings'] ?? null )
            ? $attrs['metadata']['bindings'] : [];
        $result = [];

        foreach( $bindings as $attr => $binding )
        {
            if( is_string( $attr ) && is_array( $binding ) && ( $binding['source'] ?? null ) === 'core/post-meta'
                && is_array( $binding['args'] ?? null ) && is_string( $key = $binding['args']['key'] ?? null ) && $key !== ''
                && !str_starts_with( $key, '_' ) )
            {
                $result[$attr] = $key;
            }
        }

        return $result;
    }


    /**
     * Returns the number of imported posts.
     */
    public function count(): int
    {
        return $this->posts()->count();
    }


    /**
     * Returns the slugs, titles and dates of the imported posts, e.g. for the links between the posts and their permalinks.
     *
     * @return array<int|string, object{post_name: string, post_title: string, post_date: string}> Slugs, titles and local dates by post ID
     */
    public function names(): array
    {
        $names = [];

        foreach( $this->posts()->select( ['ID', 'post_name', 'post_title', 'post_date'] )->cursor() as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $data['ID'] ?? null ) || is_string( $data['ID'] ?? null ) ) && is_string( $data['post_name'] ?? null ) && is_string( $data['post_title'] ?? null ) ) {
                $names[$data['ID']] = (object) ['post_name' => $data['post_name'], 'post_title' => $data['post_title'], 'post_date' => is_string( $data['post_date'] ?? null ) ? $data['post_date'] : ''];
            }
        }

        return $names;
    }


    /**
     * Returns the URLs of the site from the "home" and "siteurl" options, the links of the posts use them.
     *
     * @return array<int, string> URLs without trailing slash, empty if the options aren't available
     */
    public function siteUrls(): array
    {
        try {
            $urls = $this->db->table( $this->prefix . 'options' )->whereIn( 'option_name', ['home', 'siteurl'] )->pluck( 'option_value' );
        } catch( \Illuminate\Database\QueryException $e ) {
            return [];
        }

        return array_values( array_unique( array_filter( $urls->map( fn( $url ) => is_string( $url ) ? rtrim( trim( $url ), '/' ) : '' )->all() ) ) );
    }


    /**
     * Returns the roles whose users can read private posts like WordPress' "read_private_posts" capability.
     *
     * The roles are stored serialized in the "<prefix>user_roles" option, administrators and editors have the capability
     * by default.
     *
     * @return array<int, string> Role names, the default roles if the option isn't available or invalid
     */
    public function privateRoles(): array
    {
        try {
            $value = $this->db->table( $this->prefix . 'options' )->where( 'option_name', $this->prefix . 'user_roles' )->value( 'option_value' );
        } catch( \Illuminate\Database\QueryException $e ) {
            $value = null;
        }

        $roles = is_string( $value ) ? @unserialize( $value, ['allowed_classes' => false] ) : null;

        if( !is_array( $roles ) ) {
            return ['administrator', 'editor'];
        }

        return array_values( array_map( strval( ... ), array_keys( array_filter( $roles, fn( $role ) => is_array( $role )
            && is_array( $role['capabilities'] ?? null ) && !empty( $role['capabilities']['read_private_posts'] ) ) ) ) );
    }


    /**
     * Returns the values of the SEO plugins stored in the post meta of the posts.
     *
     * Yoast SEO and Rank Math store the title, description, social media texts, canonical URL and robots settings
     * of the posts in the post meta, see SEO_KEYS. Only the values of the active plugins are used because the post
     * meta of deactivated plugins stays but isn't shown by WordPress, see seoPlugins().
     *
     * @param array<int, int|string> $postIds IDs of the posts of one chunk
     * @return array<int|string, array<string, string>> Values by meta key by post ID
     */
    public function seo( array $postIds ): array
    {
        $result = [];

        if( $postIds === [] ) {
            return [];
        }

        // the primary categories are used by the variables of the SEO plugins like "%%primary_category%%"
        if( ( $keys = $this->seoKeys( [...self::SEO_KEYS, ...self::PRIMARY_KEYS] ) ) === [] ) {
            return [];
        }

        $rows = $this->db->table( $this->prefix . 'postmeta' )->whereIn( 'meta_key', $keys )->whereIn( 'post_id', $postIds )
            ->get( ['post_id', 'meta_key', 'meta_value'] );

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $id = $data['post_id'] ?? null ) || is_string( $id ) ) && is_string( $key = $data['meta_key'] ?? null )
                && is_string( $value = $data['meta_value'] ?? null ) && trim( $value ) !== '' )
            {
                $result[$id][$key] = trim( $value );
            }
        }

        return $result;
    }


    /**
     * Returns the names of the categories and tags of the posts.
     *
     * The default category like "Uncategorized", which WordPress assigns to posts without category, is left out.
     * Sites without the taxonomy tables return no terms.
     *
     * @param array<int, int|string> $postIds IDs of the posts of one chunk
     * @return array<int|string, array<int, string>> Category and tag names by post ID
     */
    public function terms( array $postIds ): array
    {
        if( $postIds === [] || $this->terms === false ) {
            return [];
        }

        try
        {
            $this->terms ??= (int) $this->db->table( $this->prefix . 'options' )->where( 'option_name', 'default_category' )->value( 'option_value' );

            $rows = $this->db->table( $this->prefix . 'term_relationships AS tr' )
                ->join( $this->prefix . 'term_taxonomy AS tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id' )
                ->join( $this->prefix . 'terms AS t', 't.term_id', '=', 'tt.term_id' )
                ->whereIn( 'tt.taxonomy', ['category', 'post_tag'] )->whereIn( 'tr.object_id', $postIds )
                ->where( fn( $query ) => $query->where( 'tt.taxonomy', 'post_tag' )->orWhere( 't.term_id', '!=', $this->terms ) )
                ->orderBy( 'tt.taxonomy' )->orderBy( 'tr.term_order' )->orderBy( 't.name' )
                ->get( ['tr.object_id', 't.name'] );
        }
        catch( \Illuminate\Database\QueryException $e )
        {
            $this->terms = false;
            return [];
        }

        $result = [];

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $id = $data['object_id'] ?? null ) || is_string( $id ) ) && is_string( $name = $data['name'] ?? null ) && trim( $name ) !== '' ) {
                $result[$id][] = html_entity_decode( trim( $name ), ENT_QUOTES, 'UTF-8' );
            }
        }

        return array_map( fn( $names ) => array_values( array_unique( $names ) ), $result );
    }


    /**
     * Returns if search engines are discouraged from indexing the site by the "blog_public" option.
     *
     * WordPress adds "noindex, nofollow" to all pages if the option is "0", see wp_robots_no_robots().
     */
    public function hidden(): bool
    {
        try {
            return $this->db->table( $this->prefix . 'options' )->where( 'option_name', 'blog_public' )->value( 'option_value' ) === '0';
        } catch( \Illuminate\Database\QueryException $e ) {
            return false;
        }
    }


    /**
     * Returns the language code of the site from the "WPLANG" option like get_locale() of WordPress.
     *
     * The option contains the locale like "de_DE" or "de_DE_formal", which is empty for the default "en_US". Sites
     * of a multisite network without own option use the language of the network from the "sitemeta" table.
     *
     * @return string|null Lower case ISO 639 language code like "de" or NULL if the option isn't available
     */
    public function language(): ?string
    {
        try {
            $locale = $this->db->table( $this->prefix . 'options' )->where( 'option_name', 'WPLANG' )->value( 'option_value' );
        } catch( \Illuminate\Database\QueryException $e ) {
            return null;
        }

        if( $locale === null )
        {
            try {
                $locale = $this->db->table( preg_replace( '/\d+_$/', '', $this->prefix ) . 'sitemeta' )->where( 'meta_key', 'WPLANG' )->value( 'meta_value' );
            } catch( \Illuminate\Database\QueryException $e ) {
                return null;
            }
        }

        if( !is_string( $locale ) ) {
            return null;
        }

        return trim( $locale ) === '' ? 'en' : ( preg_match( '/^([a-z]{2,3})(?:[_-]|$)/i', trim( $locale ), $m ) ? strtolower( $m[1] ) : null );
    }


    /**
     * Returns the options of the site used by the templates and default settings of the SEO plugins.
     *
     * @return array{name: string, description: string, dateFormat: string, yoast: array<mixed>, rankMath: array<mixed>, plugins: array<int, string>|null}
     *  Site title and tagline from the "blogname" and "blogdescription" options, the date format from "date_format", the title settings
     *  of Yoast SEO ("wpseo_titles") and Rank Math ("rank-math-options-titles"), empty if the options aren't available, and the active
     *  plugins of the site and the network ("active_plugins" and "active_sitewide_plugins"), NULL if unknown
     */
    public function site(): array
    {
        try {
            $options = $this->db->table( $this->prefix . 'options' )
                ->whereIn( 'option_name', ['blogname', 'blogdescription', 'date_format', 'wpseo_titles', 'rank-math-options-titles'] )
                ->pluck( 'option_value', 'option_name' );
        } catch( \Illuminate\Database\QueryException $e ) {
            $options = collect();
        }

        $text = fn( string $name ) => is_string( $value = $options[$name] ?? null ) ? html_entity_decode( trim( $value ), ENT_QUOTES, 'UTF-8' ) : '';

        return [
            'name' => $text( 'blogname' ),
            'description' => $text( 'blogdescription' ),
            'dateFormat' => $text( 'date_format' ),
            'yoast' => self::unserialized( $options['wpseo_titles'] ?? null ),
            'rankMath' => self::unserialized( $options['rank-math-options-titles'] ?? null ),
            'plugins' => $this->plugins(),
        ];
    }


    /**
     * Returns the SEO plugins whose settings are used.
     *
     * The settings of deactivated plugins stay in the options and post meta, but WordPress doesn't use them.
     * If the active plugins are unknown, the settings of all plugins are used.
     *
     * @param array<int, string>|null $plugins Active plugins like "wordpress-seo/wp-seo.php" or NULL if unknown, see site()
     * @return array<int, string> Keys of SEO_PLUGINS, i.e. "yoast" and "rankMath"
     */
    public static function seoPlugins( ?array $plugins ): array
    {
        return array_keys( array_filter( self::SEO_PLUGINS, fn( $pattern ) => $plugins === null || preg_grep( $pattern, $plugins ) ) );
    }


    /**
     * Returns the active plugins of the site and the network.
     *
     * @return array<int, string>|null Plugin files from the "active_plugins" and "active_sitewide_plugins" options or NULL if unknown
     */
    protected function plugins(): ?array
    {
        if( $this->plugins === null )
        {
            try {
                $value = $this->db->table( $this->prefix . 'options' )->where( 'option_name', 'active_plugins' )->value( 'option_value' );
            } catch( \Illuminate\Database\QueryException $e ) {
                $value = null;
            }

            $this->plugins = $value !== null ? array_values( array_filter( self::unserialized( $value ), 'is_string' ) ) : false;

            // plugins activated for all sites of a multisite network are the keys of the "active_sitewide_plugins" network option
            if( $this->plugins !== false )
            {
                try {
                    $network = $this->db->table( preg_replace( '/\d+_$/', '', $this->prefix ) . 'sitemeta' )->where( 'meta_key', 'active_sitewide_plugins' )->value( 'meta_value' );
                    $this->plugins = [...$this->plugins, ...array_filter( array_keys( self::unserialized( $network ) ), 'is_string' )];
                } catch( \Illuminate\Database\QueryException $e ) {
                    // single sites have no network options
                }
            }
        }

        return $this->plugins ?: ( $this->plugins === false ? null : [] );
    }


    /**
     * Returns the post meta keys of the active SEO plugins.
     *
     * @param array<int, string> $keys Post meta keys of Yoast SEO starting with "_yoast" and of Rank Math
     * @return array<int, string> Keys of the active plugins
     */
    protected function seoKeys( array $keys ): array
    {
        $active = self::seoPlugins( $this->plugins() );
        return array_values( array_filter( $keys, fn( $key ) => in_array( str_starts_with( $key, '_yoast' ) ? 'yoast' : 'rankMath', $active, true ) ) );
    }


    /**
     * Returns the unserialized array of an option value.
     *
     * @return array<mixed> Array or empty if the value isn't a serialized array
     */
    protected static function unserialized( mixed $value ): array
    {
        return is_string( $value ) && is_array( $value = @unserialize( $value, ['allowed_classes' => false] ) ) ? $value : [];
    }


    /**
     * Returns the values of the posts used by the variables of the SEO plugins like "%%category%%" or "%%name%%".
     *
     * Categories and tags are ordered by name like get_the_terms() returns them, the default category isn't left out.
     * Values which aren't available, e.g. if the taxonomy or users tables are missing, are left out.
     *
     * @param array<int, int|string> $postIds IDs of the posts of one chunk
     * @return array<int|string, array{categories?: array<int, string>, tags?: array<int, string>, author?: string, modified?: string}>
     *  Category names by term ID, tag names, display name of the author and local modification date by post ID
     */
    public function variables( array $postIds ): array
    {
        if( $postIds === [] ) {
            return [];
        }

        $result = [];

        try
        {
            $rows = $this->db->table( $this->prefix . 'term_relationships AS tr' )
                ->join( $this->prefix . 'term_taxonomy AS tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id' )
                ->join( $this->prefix . 'terms AS t', 't.term_id', '=', 'tt.term_id' )
                ->whereIn( 'tt.taxonomy', ['category', 'post_tag'] )->whereIn( 'tr.object_id', $postIds )
                ->orderBy( 't.name' )->orderBy( 't.term_id' )
                ->get( ['tr.object_id', 'tt.taxonomy', 't.term_id', 't.name'] );

            foreach( $postIds as $id ) {
                $result[$id] = ['categories' => [], 'tags' => []];
            }

            foreach( $rows as $row )
            {
                $data = get_object_vars( $row );

                if( ( is_int( $id = $data['object_id'] ?? null ) || is_string( $id ) ) && isset( $result[$id] ) && is_numeric( $data['term_id'] ?? null )
                    && is_string( $name = $data['name'] ?? null ) && ( $name = html_entity_decode( trim( $name ), ENT_QUOTES, 'UTF-8' ) ) !== '' )
                {
                    if( ( $data['taxonomy'] ?? null ) === 'category' ) {
                        $result[$id]['categories'][(int) $data['term_id']] = $name;
                    } elseif( !in_array( $name, $result[$id]['tags'], true ) ) {
                        $result[$id]['tags'][] = $name;
                    }
                }
            }
        }
        catch( \Illuminate\Database\QueryException $e )
        {
            // sites without the taxonomy tables have no categories and tags
        }

        $posts = $this->prefix . 'posts';

        try {
            $rows = $this->db->table( $posts )->leftJoin( $this->users() . ' AS u', 'u.ID', '=', $posts . '.post_author' )
                ->whereIn( $posts . '.ID', $postIds )->get( [$posts . '.ID', $posts . '.post_modified', 'u.display_name'] );
        } catch( \Illuminate\Database\QueryException $e ) {
            $rows = [];
        }

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( !is_int( $id = $data['ID'] ?? null ) && !is_string( $id ) ) {
                continue;
            }

            if( is_string( $name = $data['display_name'] ?? null ) && ( $name = html_entity_decode( trim( $name ), ENT_QUOTES, 'UTF-8' ) ) !== '' ) {
                $result[$id]['author'] = $name;
            }

            if( is_string( $modified = $data['post_modified'] ?? null ) && ( $modified = Date::first( [$modified] ) ) ) {
                $result[$id]['modified'] = $modified;
            }
        }

        return $result;
    }


    /**
     * Returns the category paths of the imported posts used by permalinks with "%category%".
     *
     * Like get_permalink(), the category with the lowest ID is used, or the primary category of Yoast SEO and Rank
     * Math if set and the plugin is active (the "post_link_category" filter of the plugins), prefixed by the slugs of
     * its parent categories.
     * Posts without category use the default category.
     *
     * @return array<int|string, string>|null Decoded category paths like "news/local" by post ID or NULL if the taxonomy tables aren't available
     */
    public function categoryPaths(): ?array
    {
        try
        {
            $categories = $this->db->table( $this->prefix . 'term_taxonomy AS tt' )
                ->join( $this->prefix . 'terms AS t', 't.term_id', '=', 'tt.term_id' )
                ->where( 'tt.taxonomy', 'category' )->get( ['t.term_id', 't.slug', 'tt.parent'] );
            $relations = $this->db->table( $this->prefix . 'term_relationships AS tr' )
                ->join( $this->prefix . 'term_taxonomy AS tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id' )
                ->where( 'tt.taxonomy', 'category' )->whereIn( 'tr.object_id', $this->posts()->reorder()->select( 'ID' ) )
                ->orderBy( 'tt.term_id' )->get( ['tr.object_id', 'tt.term_id'] );
            $default = (int) $this->db->table( $this->prefix . 'options' )->where( 'option_name', 'default_category' )->value( 'option_value' );
        }
        catch( \Illuminate\Database\QueryException $e )
        {
            return null;
        }

        $tree = [];

        foreach( $categories as $row )
        {
            $data = get_object_vars( $row );

            if( is_numeric( $data['term_id'] ?? null ) && is_string( $data['slug'] ?? null ) ) {
                $tree[(int) $data['term_id']] = ['slug' => rawurldecode( $data['slug'] ), 'parent' => is_numeric( $data['parent'] ?? null ) ? (int) $data['parent'] : 0];
            }
        }

        $postCats = [];

        foreach( $relations as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $id = $data['object_id'] ?? null ) || is_string( $id ) ) && is_numeric( $data['term_id'] ?? null ) ) {
                $postCats[$id] ??= (int) $data['term_id'];
            }
        }

        // the primary categories are only used by the active plugins
        $primary = ( $keys = $this->seoKeys( self::PRIMARY_KEYS ) ) ? $this->db->table( $this->prefix . 'postmeta' )->whereIn( 'meta_key', $keys )
            ->whereIn( 'post_id', $this->posts()->reorder()->select( 'ID' ) )->orderBy( 'meta_key' )->get( ['post_id', 'meta_value'] ) : collect();

        // Yoast SEO is ordered before Rank Math, so its primary category wins if both are set
        foreach( $primary->reverse() as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $id = $data['post_id'] ?? null ) || is_string( $id ) ) && is_numeric( $data['meta_value'] ?? null ) && isset( $tree[(int) $data['meta_value']] ) ) {
                $postCats[$id] = (int) $data['meta_value'];
            }
        }

        $paths = [];

        foreach( $this->posts()->reorder()->pluck( 'ID' ) as $id )
        {
            $termId = $postCats[$id] ?? $default;
            $slugs = [];

            // the parents are limited to the number of categories in case of loops
            for( $i = 0; isset( $tree[$termId] ) && $i <= count( $tree ); $i++, $termId = $tree[$termId]['parent'] ) {
                array_unshift( $slugs, $tree[$termId]['slug'] );
            }

            if( $slugs ) {
                $paths[$id] = implode( '/', $slugs );
            }
        }

        return $paths;
    }


    /**
     * Returns the author slugs of the imported posts used by permalinks with "%author%".
     *
     * The users table of multisite networks is shared by all sites, so it has the prefix of the network.
     *
     * @return array<int|string, string>|null Decoded "user_nicename" of the authors by post ID or NULL if the users table isn't available
     */
    public function authors(): ?array
    {
        try {
            $rows = $this->posts()->reorder()->join( $this->users() . ' AS u', 'u.ID', '=', $this->prefix . 'posts.post_author' )
                ->get( [$this->prefix . 'posts.ID', 'u.user_nicename'] );
        } catch( \Illuminate\Database\QueryException $e ) {
            return null;
        }

        $authors = [];

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( ( is_int( $id = $data['ID'] ?? null ) || is_string( $id ) ) && is_string( $name = $data['user_nicename'] ?? null ) && $name !== '' ) {
                $authors[$id] = rawurldecode( $name );
            }
        }

        return $authors;
    }


    /**
     * Returns the former dates of the posts whose date was changed.
     *
     * WordPress stores them in the "_wp_old_date" post meta and redirects permalinks with the former date, see
     * wp_check_for_changed_dates() and _find_post_by_old_date().
     *
     * @return array<int|string, array<int, string>> Former dates like "2026-01-15" by post ID
     */
    public function oldDates(): array
    {
        $dates = [];
        $rows = $this->db->table( $this->prefix . 'postmeta' )->where( 'meta_key', '_wp_old_date' )
            ->whereIn( 'post_id', $this->posts()->reorder()->select( 'ID' ) )->get( ['post_id', 'meta_value'] );

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( is_string( $date = $data['meta_value'] ?? null ) && preg_match( '/^[1-9]\d{3}-\d{2}-\d{2}$/', trim( $date ) ) && ( is_int( $id = $data['post_id'] ?? null ) || is_string( $id ) ) ) {
                $dates[$id][] = trim( $date );
            }
        }

        return array_map( fn( $list ) => array_values( array_unique( $list ) ), $dates );
    }


    /**
     * Returns the permalink settings of the site, the links of the posts and archives use them.
     *
     * @return array{structure: string, bases: array<int, string>} Permalink structure of the posts like "/%year%/%postname%/"
     *  and the paths of the category and tag archives, empty if the options aren't available or not set
     */
    public function permalinks(): array
    {
        try {
            $options = $this->db->table( $this->prefix . 'options' )->whereIn( 'option_name', ['permalink_structure', 'category_base', 'tag_base'] )
                ->pluck( 'option_value', 'option_name' );
        } catch( \Illuminate\Database\QueryException $e ) {
            return ['structure' => '', 'bases' => ['', '']];
        }

        $value = fn( string $name ) => is_string( $value = $options[$name] ?? null ) ? trim( $value ) : '';
        return ['structure' => $value( 'permalink_structure' ), 'bases' => [$value( 'category_base' ), $value( 'tag_base' )]];
    }


    /**
     * Returns the rows of the imported posts one by one.
     *
     * @return LazyCollection<int, \stdClass>
     */
    public function cursor(): LazyCollection
    {
        return $this->posts()->select( self::POST_COLUMNS )->cursor();
    }


    /**
     * Validates a WordPress post database row.
     *
     * @return WpPost
     */
    public function post( object $post ): object
    {
        $data = get_object_vars( $post );
        $id = $data['ID'] ?? null;
        $name = $data['post_name'] ?? null;
        $title = $data['post_title'] ?? null;
        $excerpt = $data['post_excerpt'] ?? null;
        $content = $data['post_content'] ?? null;
        $dateGmt = $data['post_date_gmt'] ?? null;
        $date = $data['post_date'] ?? null;
        $modified = $data['post_modified_gmt'] ?? null;
        $password = $data['post_password'] ?? '';
        $status = $data['post_status'] ?? 'publish';

        if( is_string( $id ) && ctype_digit( $id ) ) {
            $id = filter_var( $id, FILTER_VALIDATE_INT );
        }

        if( !is_int( $id ) || !is_string( $name ) || !is_string( $title ) || !is_string( $excerpt )
            || !is_string( $content ) || !( is_string( $dateGmt ) || $dateGmt === null ) || !is_string( $date )
            || !( is_string( $modified ) || $modified === null ) || !is_string( $password ) || !is_string( $status ) ) {
            throw new \UnexpectedValueException( 'Invalid WordPress post row.' );
        }

        return (object) [
            'ID' => $id,
            'post_name' => $name,
            'post_title' => $title,
            'post_excerpt' => $excerpt,
            'post_content' => $content,
            'post_date_gmt' => $dateGmt,
            'post_date' => $date,
            'post_modified_gmt' => $modified,
            'post_password' => $password,
            'post_status' => $status,
        ];
    }


    /**
     * Returns the former slugs of the imported posts, WordPress redirects them to the current URL of the post.
     *
     * WordPress remembers the slug in the "_wp_old_slug" post meta when the slug of a published post changes,
     * see wp_check_for_changed_slugs(), and redirects requests for it by wp_old_slug_redirect().
     *
     * @return array<int|string, array<int, string>> Former slugs by post ID
     */
    public function oldSlugs(): array
    {
        $slugs = [];
        $rows = $this->db->table( $this->prefix . 'postmeta' )->where( 'meta_key', '_wp_old_slug' )
            ->whereIn( 'post_id', $this->posts()->reorder()->select( 'ID' ) )
            ->orderBy( 'meta_id' )->get( ['post_id', 'meta_value'] );

        foreach( $rows as $row )
        {
            $data = get_object_vars( $row );

            if( is_string( $slug = $data['meta_value'] ?? null ) && trim( $slug ) !== '' && ( is_int( $id = $data['post_id'] ?? null ) || is_string( $id ) ) ) {
                $slugs[$id][] = trim( $slug );
            }
        }

        return $slugs;
    }


    /**
     * Returns the IDs of the posts using reusable blocks (synced patterns) changed since the given time.
     *
     * WordPress shows the current content of the reusable blocks in all posts using them, but changing a
     * reusable block doesn't change the modification date of the posts. Reusable blocks used by changed
     * reusable blocks are changed too.
     *
     * @param int $since Timestamp of the --since option
     * @return array<int|string, bool> Post IDs as keys
     */
    public function patternPosts( int $since ): array
    {
        $blocks = $this->db->table( $this->prefix . 'posts' )->where( 'post_type', 'wp_block' )
            ->get( ['ID', 'post_content', 'post_modified_gmt'] );
        $refs = fn( string $content, array $ids ) => array_filter( $ids, fn( $id ) => preg_match( '/<!--\s*wp:(?:core\/)?block\s+\{[^}]*"ref":\s*' . $id . '\b/', $content ) === 1 );
        $changed = [];

        foreach( $blocks as $block )
        {
            $data = get_object_vars( $block );
            $time = is_string( $data['post_modified_gmt'] ?? null ) && ( $date = Date::first( [$data['post_modified_gmt']] ) ) ? strtotime( $date . ' UTC' ) : false;

            if( $time === false || $time >= $since ) {
                $changed[(int) $data['ID']] = (int) $data['ID'];
            }
        }

        // reusable blocks using changed ones are changed too
        do {
            $count = count( $changed );

            foreach( $blocks as $block )
            {
                $data = get_object_vars( $block );

                if( !isset( $changed[(int) $data['ID']] ) && $changed && $refs( (string) $data['post_content'], $changed ) ) {
                    $changed[(int) $data['ID']] = (int) $data['ID'];
                }
            }
        } while( count( $changed ) > $count );

        $posts = [];

        foreach( array_chunk( $changed, 100 ) as $ids )
        {
            $query = $this->posts()->reorder()->where( fn( $query ) => array_map( fn( $id ) => $query->orWhere( 'post_content', 'like', '%"ref":' . $id . '%' ), $ids ) );

            foreach( $query->select( ['ID', 'post_content'] )->cursor() as $row )
            {
                $data = get_object_vars( $row );

                if( $refs( (string) $data['post_content'], $ids ) ) {
                    $posts[$data['ID']] = true;
                }
            }
        }

        return $posts;
    }


    /**
     * Fetches the attachment IDs of the featured images (post thumbnails) of the posts.
     *
     * @param array<int, int> $postIds IDs of the posts of one chunk
     * @return array<int|string, int|string> Attachment IDs by post ID
     */
    public function thumbnails( array $postIds ): array
    {
        return $this->db->table( $this->prefix . 'postmeta' )
            ->where( 'meta_key', '_thumbnail_id' )
            ->whereIn( 'post_id', $postIds )
            ->pluck( 'meta_value', 'post_id' )
            ->all();
    }


    /**
     * Validates a WordPress attachment database row.
     *
     * @return array{guid: string, title: string, mime: string, url: string, alt: string}
     */
    protected function attachment( object $attachment ): array
    {
        $data = get_object_vars( $attachment );
        $guid = $data['guid'] ?? null;
        $title = $data['post_title'] ?? null;
        $mime = $data['post_mime_type'] ?? null;
        $file = $data['attached_file'] ?? null;
        $alt = $data['alt'] ?? null;

        if( !is_string( $guid ) || !is_string( $title ) || !is_string( $mime ) || !( is_string( $file ) || $file === null ) || !( is_string( $alt ) || $alt === null ) ) {
            throw new \UnexpectedValueException( 'Invalid WordPress attachment row.' );
        }

        return ['guid' => $guid, 'title' => $title, 'mime' => $mime, 'url' => $this->attachedUrl( $guid, (string) $file ), 'alt' => trim( (string) $alt )];
    }


    /**
     * Returns the URL of the attachment file like wp_get_attachment_url() of WordPress.
     *
     * The file is relative to the uploads directory whose base URL is taken from the "upload_url_path",
     * "upload_path" and "siteurl" options. The guid isn't updated if the site moves to another domain,
     * so it's only used if the options aren't available.
     *
     * @param string $guid URL of the attachment at the time of the upload
     * @param string $file Value of the "_wp_attached_file" post meta like "2026/01/photo-scaled.jpg"
     */
    protected function attachedUrl( string $guid, string $file ): string
    {
        if( ( $file = trim( $file ) ) === '' ) {
            return $guid;
        }

        if( preg_match( '#^https?://#i', $file ) ) {
            return $file;
        }

        // very old attachments contain the absolute path of the file on the server
        $file = (string) preg_replace( '#^.*?/wp-content/uploads/#i', '', '/' . ltrim( $file, '/' ) );
        $file = ltrim( $file, '/' );

        if( ( $base = $this->uploads() ) !== null ) {
            return $base . '/' . $file;
        }

        $path = (string) parse_url( $guid, PHP_URL_PATH );

        if( $path !== '' && ( rawurldecode( $path ) === '/' . $file || str_ends_with( rawurldecode( $path ), '/' . $file ) ) ) {
            return $guid;
        }

        if( preg_match( '#^(https?://[^?\#]*?/wp-content/uploads(?:/sites/\d+)?)/#i', $guid, $m ) ) {
            return $m[1] . '/' . $file;
        }

        return $guid;
    }


    /**
     * Applies the overrides of a synced pattern like the "core/pattern-overrides" block bindings of WordPress 6.5+.
     *
     * The values are stored by the name of the block in the "content" attribute of the reference like
     * "<!-- wp:block {"ref":12,"content":{"Title":{"content":"Text"}}} /-->". Only paragraph, heading,
     * list item, button and image blocks including the image caption can be overridden and only the
     * attributes bound to "core/pattern-overrides" in the pattern, either all by "__default" or one by one.
     *
     * @param string $content Content of the reusable block
     * @param string $json Attributes of the reference
     */
    protected function overrides( string $content, string $json ): string
    {
        $attrs = json_decode( $json, true );

        // the "overrides" attribute is used by older versions of the block editor
        if( !is_array( $attrs ) || !is_array( $overrides = $attrs['content'] ?? $attrs['overrides'] ?? null ) || $overrides === [] ) {
            return $content;
        }

        return $this->bound( $content, function( string $type, string $json ) use ( $overrides ) {
            $attrs = json_decode( $json, true );
            $meta = is_array( $attrs ) && is_array( $attrs['metadata'] ?? null ) ? $attrs['metadata'] : [];
            $bindings = is_array( $meta['bindings'] ?? null ) ? $meta['bindings'] : [];
            $values = is_string( $name = $meta['name'] ?? null ) ? $overrides[$name] ?? null : null;

            // values of WordPress 6.5 are nested in "values"
            if( !is_array( $values = is_array( $values ) && is_array( $values['values'] ?? null ) ? $values['values'] : $values ) ) {
                return [];
            }

            // "__default" binds all supported attributes which aren't bound to other sources
            $default = ( $bindings['__default']['source'] ?? null ) === 'core/pattern-overrides';
            $allowed = array_filter( self::BINDABLE[$type] ?? [], function( $attr ) use ( $bindings, $default ) {
                $source = is_array( $bindings[$attr] ?? null ) ? $bindings[$attr]['source'] ?? null : null;
                return $source === 'core/pattern-overrides' || $default && $source === null;
            } );

            return array_intersect_key( $values, array_flip( $allowed ) );
        } );
    }


    /**
     * Replaces the texts and attributes of the paragraph, heading, list item, button and image blocks by the bound values.
     *
     * @param string $content Content with blocks
     * @param \Closure $values Returns the bound values by attribute name like "content" or "url" for the block type and its JSON attributes
     */
    protected function bound( string $content, \Closure $values ): string
    {
        $content = (string) preg_replace_callback( self::BOUND_BLOCKS, function( $m ) use ( $values ) {
            return ( $list = $values( $m[2], $m[3] ) ) ? $m[1] . $this->override( $m[2], $m[4], $list ) . $m[5] : $m[0];
        }, $content );

        // list items can contain nested lists, so only their own text up to a nested list or the end of the item is replaced
        return (string) preg_replace_callback( self::BOUND_ITEMS, function( $m ) use ( $values ) {
            return is_string( $text = $values( 'list-item', $m[2] )['content'] ?? null ) ? $m[1] . $text : $m[0];
        }, $content );
    }


    /**
     * Replaces the text and the attributes of an overridden block in a synced pattern.
     *
     * @param string $type Block type, i.e. "paragraph", "heading", "button" or "image"
     * @param string $html HTML of the block
     * @param array<mixed> $values Overridden attributes of the block like "content", "text", "url", "alt", "caption" or "id"
     */
    protected function override( string $type, string $html, array $values ): string
    {
        // like WordPress, images without URL aren't shown
        if( $type === 'image' && ( $values['url'] ?? null ) === '' ) {
            return '';
        }

        $tag = match( $type ) { 'paragraph' => 'p', 'heading' => 'h[1-6]', 'button' => 'a', default => null };
        $text = $values['content'] ?? $values['text'] ?? null;

        if( $tag && is_string( $text ) ) {
            $html = (string) preg_replace_callback( '/(<(' . $tag . ')\b[^>]*>).*(<\/\2>)/is', fn( $m ) => $m[1] . $text . $m[3], $html, 1 );
        }

        $attrs = match( $type ) { 'button' => ['url' => ['a', 'href']], 'image' => ['url' => ['img', 'src'], 'alt' => ['img', 'alt']], default => [] };

        foreach( $attrs as $key => [$el, $name] )
        {
            if( !is_string( $value = $values[$key] ?? null ) ) {
                continue;
            }

            $value = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
            $html = (string) preg_replace_callback( '/<' . $el . '\b[^>]*>/i', function( $m ) use ( $el, $name, $value ) {
                $tag = (string) preg_replace_callback( '/\s' . $name . '=(?:"[^"]*"|\'[^\']*\')/i', fn() => ' ' . $name . '="' . $value . '"', $m[0], 1, $count );
                return $count ? $tag : '<' . $el . ' ' . $name . '="' . $value . '"' . substr( $m[0], strlen( $el ) + 1 );
            }, $html, 1 );
        }

        // the caption is the text of the image in Pagible, like WordPress only an existing caption is replaced
        if( $type === 'image' && is_string( $caption = $values['caption'] ?? null ) ) {
            $html = (string) preg_replace_callback( '/(<figcaption\b[^>]*>).*?(<\/figcaption>)/is', fn( $m ) => $m[1] . $caption . $m[2], $html, 1 );
        }

        // the attachment ID is used to find the imported image
        if( $type === 'image' && ( is_int( $id = $values['id'] ?? null ) || is_string( $id ) && ctype_digit( $id ) ) ) {
            $html = (string) preg_replace_callback( '/\bwp-image-\d+\b/', fn() => 'wp-image-' . $id, $html, 1 );
        }

        return $html;
    }


    /**
     * Returns TRUE if the images in the "ids" or "include" attribute of the gallery shortcode are sorted by WordPress.
     *
     * The "ids" are shown in their order without "orderby" attribute, the images in the "include" attribute of old
     * galleries are sorted by "menu_order ID" by default. Random order and unknown fields keep the order of the IDs.
     *
     * @param array<string, string> $attrs Attributes of the gallery shortcode with attachment IDs
     */
    protected function gallerySorted( array $attrs ): bool
    {
        if( ( $orderby = trim( $attrs['orderby'] ?? '' ) ) === '' ) {
            return trim( $attrs['ids'] ?? '' ) === '';
        }

        return $this->galleryKeys( $orderby ) !== [];
    }


    /**
     * Returns the columns of the attachments the "orderby" attribute of gallery shortcodes sorts by.
     *
     * @param string $orderby Space or comma separated fields like "menu_order ID" or "title"
     * @return array<int, string> Column names, empty for random order and unknown fields
     */
    protected function galleryKeys( string $orderby ): array
    {
        $map = [
            'menu_order' => 'menu_order', 'id' => 'ID', 'title' => 'post_title', 'post_title' => 'post_title',
            'name' => 'post_name', 'post_name' => 'post_name', 'date' => 'post_date', 'post_date' => 'post_date',
            'modified' => 'post_modified', 'post_modified' => 'post_modified',
        ];

        return array_values( array_filter( array_map( fn( $key ) => $map[strtolower( $key )] ?? null, preg_split( '/[\s,]+/', $orderby ) ?: [] ) ) );
    }


    /**
     * Returns the attributes of the not escaped shortcodes with the tag in the post content.
     *
     * @param string $tag Name of the shortcode, one of Shortcode::TAGS
     * @return array<int, array<string, string>> Attributes of the shortcodes
     */
    protected function shortcodes( string $content, string $tag ): array
    {
        if( !str_contains( $content, '[' . $tag ) || !preg_match_all( Shortcode::pattern(), $content, $matches, PREG_SET_ORDER ) ) {
            return [];
        }

        $list = [];

        foreach( $matches as $m )
        {
            if( $m[2] === $tag && $m[1] !== '[' ) {
                $list[] = Shortcode::attributes( $m[3] );
            }
        }

        return $list;
    }


    /**
     * Returns the sorted IDs of the images attached to the post of a gallery shortcode.
     *
     * @param array<int, array<string, mixed>> $images Attached images with ID, menu order, title, name and dates
     * @param array<string, string> $attrs Attributes of the gallery shortcode
     * @return array<int, int> Attachment IDs
     */
    protected function galleryIds( array $images, array $attrs ): array
    {
        $keys = $this->galleryKeys( $attrs['orderby'] ?? '' ) ?: ['menu_order', 'ID'];
        $dir = strtoupper( trim( $attrs['order'] ?? '' ) ) === 'DESC' ? -1 : 1;
        $exclude = Shortcode::ids( ['ids' => $attrs['exclude'] ?? ''] );

        usort( $images, function( $a, $b ) use ( $keys, $dir ) {
            foreach( $keys as $key )
            {
                $x = $a[$key] ?? '';
                $y = $b[$key] ?? '';
                $cmp = is_numeric( $x ) && is_numeric( $y ) ? $x <=> $y : strcmp( (string) $x, (string) $y );

                if( $cmp !== 0 ) {
                    return $cmp * $dir;
                }
            }
            return 0;
        } );

        $ids = array_map( fn( $image ) => (int) $image['ID'], $images );
        return array_values( array_diff( $ids, $exclude ) );
    }


    /**
     * Returns the ID of the post whose attached images are shown by the gallery shortcode.
     *
     * @param array<string, string> $attrs Attributes of the gallery shortcode, "id" is the ID of another post
     * @param int|string $postId ID of the post containing the shortcode
     */
    protected function galleryParent( array $attrs, int|string $postId ): int
    {
        $id = trim( $attrs['id'] ?? '' );
        return ctype_digit( $id ) && (int) $id > 0 ? (int) $id : (int) $postId;
    }


    /**
     * Returns the reusable block ID from the attributes of the block comment.
     */
    protected function ref( string $json ): int
    {
        $attrs = json_decode( $json, true );
        $ref = is_array( $attrs ) ? $attrs['ref'] ?? null : null;

        return is_int( $ref ) || is_string( $ref ) && ctype_digit( $ref ) ? (int) $ref : 0;
    }


    /**
     * Returns the base URL of the uploads from the WordPress options like wp_upload_dir().
     *
     * Sites of a multisite network use the "sites/<blog ID>" subdirectory or the "files" URL of networks with
     * ms-files rewriting. The UPLOADS and WP_CONTENT_URL constants of wp-config.php aren't available.
     *
     * @return string|null Base URL without trailing slash or NULL if the options aren't available
     */
    public function uploads(): ?string
    {
        if( $this->uploads !== false ) {
            return $this->uploads;
        }

        try {
            $options = $this->db->table( $this->prefix . 'options' )->whereIn( 'option_name', ['siteurl', 'upload_path', 'upload_url_path'] )
                ->pluck( 'option_value', 'option_name' )->map( fn( $value ) => is_string( $value ) ? trim( $value ) : '' );
        } catch( \Illuminate\Database\QueryException $e ) {
            return $this->uploads = null;
        }

        $site = rtrim( $options->get( 'siteurl', '' ), '/' );
        $network = $this->network();

        if( $network === 'files' ) {
            return $this->uploads = $site !== '' ? $site . '/files' : null;
        }

        if( ( $url = $options->get( 'upload_url_path', '' ) ) !== '' ) {
            return $this->uploads = rtrim( $url, '/' ) . $network;
        }

        if( $site === '' ) {
            return $this->uploads = null;
        }

        // absolute upload paths are only known if they are the default uploads directory of the WordPress installation
        $path = $options->get( 'upload_path', '' );

        if( str_starts_with( $path, '/' ) || preg_match( '#^[a-z]:[\\\\/]#i', $path ) )
        {
            if( !preg_match( '#[\\\\/]wp-content[\\\\/]uploads[\\\\/]?$#i', $path ) ) {
                return $this->uploads = null;
            }
            $path = '';
        }

        return $this->uploads = $site . '/' . trim( $path ?: 'wp-content/uploads', '/' ) . $network;
    }


    /**
     * Returns the uploads subdirectory of the site if the tables belong to a site of a multisite network.
     *
     * @return string "/sites/<blog ID>" for sites except the main site, "files" for networks with ms-files rewriting or empty
     */
    protected function network(): string
    {
        // the tables of the sites except the main site have the blog ID appended to the prefix of the network
        if( !preg_match( '/^(.*_)(\d+)_$/', $this->prefix, $m ) || (int) $m[2] < 2 ) {
            return '';
        }

        try
        {
            if( !$this->db->table( $m[1] . 'blogs' )->where( 'blog_id', (int) $m[2] )->exists() ) {
                return '';
            }
        }
        catch( \Illuminate\Database\QueryException $e )
        {
            return '';
        }

        try {
            $rewriting = $this->db->table( $m[1] . 'sitemeta' )->where( 'meta_key', 'ms_files_rewriting' )->value( 'meta_value' );
        } catch( \Illuminate\Database\QueryException $e ) {
            $rewriting = null;
        }

        return !empty( $rewriting ) ? 'files' : '/sites/' . $m[2];
    }


    /**
     * Returns the validated URL of a WordPress attachment database row.
     */
    protected function guid( object $attachment ): string
    {
        $guid = get_object_vars( $attachment )['guid'] ?? null;

        if( !is_string( $guid ) ) {
            throw new \UnexpectedValueException( 'Invalid WordPress attachment row.' );
        }

        return $guid;
    }


    /**
     * Returns the validated ID of a WordPress attachment database row.
     */
    protected function id( object $attachment ): int|string
    {
        $id = get_object_vars( $attachment )['ID'] ?? null;

        if( !( is_int( $id ) || is_string( $id ) ) ) {
            throw new \UnexpectedValueException( 'Invalid WordPress attachment row.' );
        }

        return $id;
    }


    /**
     * Returns the query for the audio and video attachments.
     */
    protected function media(): Builder
    {
        return $this->db->table( $this->prefix . 'posts' )
            ->where( 'post_type', 'attachment' )
            ->where( fn( $query ) => $query->where( 'post_mime_type', 'like', 'audio/%' )->orWhere( 'post_mime_type', 'like', 'video/%' ) );
    }


    /**
     * Returns the query for the image attachments.
     */
    protected function images(): Builder
    {
        return $this->db->table( $this->prefix . 'posts' )
            ->where( 'post_type', 'attachment' )
            ->whereIn( 'post_mime_type', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'] );
    }


    /**
     * Adds the texts to the post rows, the texts of the rows which aren't required are empty.
     *
     * @param array<int, \stdClass> $rows Post rows without texts
     * @param (\Closure(object): bool)|null $required Returns TRUE for the post rows whose texts are required or NULL for all rows
     */
    public function texts( array $rows, ?\Closure $required = null ): void
    {
        $ids = array_map( fn( $row ) => get_object_vars( $row )['ID'] ?? null, $required ? array_filter( $rows, $required ) : $rows );
        $texts = $ids ? $this->db->table( $this->prefix . 'posts' )->whereIn( 'ID', array_values( $ids ) )->get( ['ID', ...self::TEXT_COLUMNS] )->keyBy( 'ID' ) : null;

        foreach( $rows as $row )
        {
            $text = get_object_vars( $texts?->get( get_object_vars( $row )['ID'] ?? '' ) ?? new \stdClass );

            foreach( self::TEXT_COLUMNS as $column ) {
                $row->{$column} = $text[$column] ?? '';
            }
        }
    }


    /**
     * Returns the name of the users table, which is shared by all sites of multisite networks and has the prefix of the network.
     */
    protected function users(): string
    {
        return ( preg_match( '/^(.*_)(\d+)_$/', $this->prefix, $m ) && (int) $m[2] > 1 ? $m[1] : $this->prefix ) . 'users';
    }


    /**
     * Returns the query for the imported posts ordered by date and ID for posts of the same date.
     */
    protected function posts(): Builder
    {
        return $this->db->table( $this->prefix . 'posts' )
            ->where( 'post_type', 'post' )
            ->whereIn( 'post_status', static::STATUSES )
            ->orderBy( 'post_date', 'asc' )
            ->orderBy( 'ID', 'asc' );
    }
}
