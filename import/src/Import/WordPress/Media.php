<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\WordPress;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Str;


/**
 * Resolves WordPress media attachments and URLs to Pagible files.
 */
class Media
{
    /** @var int Maximum number of remembered MIME types of the files and attachment IDs of the image URLs */
    protected const CACHE_SIZE = 10000;
    /** Number of last characters by which the attachment basenames are indexed */
    protected const SUFFIX_SIZE = 4;
    /**
     * Suffixes of the resized (e.g. "-300x200"), scaled ("-scaled"), rotated ("-rotated") and edited ("-e" and 13 digits)
     * copies of the uploaded images before the file extension
     */
    protected const SIZE_PATTERN = '/(?:-\d+x\d+|-scaled|-rotated|-e\d{13})+(\.[a-z0-9]+)(?=[?#]|$)/i';

    /** @var array<string> */
    protected array $bases = [];
    /** @var array<string> Base URLs of the WordPress uploads like from the "upload_url_path" option, see uploads() */
    protected array $uploads = [];
    /** @var array<int, int> Position of the attachment basenames by their hash, the first matching one is used */
    protected array $basenames = [];
    /** @var array<int, int|string> Attachment IDs by position of their basename */
    protected array $ids = [];
    /** @var array<int, true> Lengths of the attachment basenames shorter than SUFFIX_SIZE */
    protected array $basenameLengths = [];
    /** @var array<string, array<int, true>> Lengths of the other attachment basenames by their last SUFFIX_SIZE characters */
    protected array $suffixLengths = [];
    /** @var array<string, int|string|null> Attachment IDs of the image URLs of the current posts, NULL if not found */
    protected array $found = [];
    /** @var array<int|string, array{guid: string, title: string, mime: string, url?: string, alt?: string}|null> Attachments of the current posts by ID, NULL if not available */
    protected array $attachments = [];
    /** @var array<int|string, int|string> Attachment IDs of the featured images by post ID */
    protected array $thumbnailIds = [];
    /** @var array<int, string> Source URLs of the files used by the current posts */
    protected array $paths = [];
    /** @var array<int, bool> TRUE if the file of the source URL at the same position is downloaded */
    protected array $managed = [];
    /** @var array<int|string, array<int, string>> Source URLs of the downloaded files by post ID */
    protected array $downloads = [];
    /** @var array<string, string> MIME types of the files by ID */
    protected array $mimes = [];
    /** @var \Closure(array<int|string>): array<int|string, array{guid: string, title: string, mime: string, url?: string, alt?: string}> */
    protected \Closure $fetch;
    /** @var iterable<int|string, int|string>|null Attachment IDs by basename until the basenames are indexed */
    protected ?iterable $attachmentIds;


    /**
     * @param array<mixed> $bases Media base URLs, the first one replaces wp-content/uploads paths
     * @param \Closure(string, string, string, bool): ?string $create Creates a file from MIME type, name, URL and download flag
     * @param iterable<int|string, int|string> $attachmentIds IDs of the WordPress image attachments by basename of their URL, later ones with the same basename replace the ID
     * @param (\Closure(array<int|string>): array<int|string, array{guid: string, title: string, mime: string, url?: string, alt?: string}>)|null $fetch Fetches the image attachments by ID
     */
    public function __construct( array $bases, protected \Closure $create, iterable $attachmentIds = [], ?\Closure $fetch = null )
    {
        foreach( $bases as $url )
        {
            if( is_string( $url ) && ( $url = rtrim( trim( $url ), '/' ) ) !== ''
                && !in_array( $url, $this->bases, true ) ) {
                $this->bases[] = $url;
            }
        }

        $this->attachmentIds = $attachmentIds;
        $this->fetch = $fetch ?? fn() => [];
    }


    /**
     * Fetches the attachments of the featured images and the images in the content of the next posts at once.
     *
     * @param array<int|string, int|string> $thumbnailIds Attachment IDs of the featured images by post ID
     * @param array<int|string, string> $contents HTML content of the posts by post ID
     */
    public function prefetch( array $thumbnailIds, array $contents = [] ): static
    {
        $ids = array_values( $thumbnailIds );
        // the attachments of the images of the next posts are looked up only once by prefetch() and image()
        $this->found = $this->paths = $this->managed = $this->downloads = $postIds = [];

        foreach( $thumbnailIds as $postId => $id ) {
            $postIds[$id][] = $postId;
        }

        foreach( $contents as $postId => $content )
        {
            // images with the attachment ID in their class and the images of gallery shortcodes are fetched by ID
            preg_match_all( '/<img\b[^>]*>/i', $content, $tags );

            foreach( [...array_filter( array_map( fn( $tag ) => $this->classId( $tag ), $tags[0] ) ), ...Shortcode::galleryIds( $content )] as $id ) {
                $ids[] = $id;
                $postIds[$id][] = $postId;
            }

            // all sources are used because the converter passes blocks and tags containing the images
            preg_match_all( '/src=["\']([^"\']+)["\']/i', $content, $matches );

            // the sources are decoded like by image() and the converter, the URLs of file blocks are JSON values without HTML entities
            $sources = [...array_map( fn( $src ) => Html::decode( $src ), $matches[1] ), ...$this->fileUrls( $content )];

            foreach( array_filter( $sources, fn( $src ) => !$this->inline( $src ) ) as $src )
            {
                if( ( $id = $this->find( $src ) ) !== null ) {
                    $ids[] = $id;
                    $postIds[$id][] = $postId;
                } else {
                    $this->add( $src, [$postId] );
                }
            }
        }

        $this->thumbnailIds = $thumbnailIds;
        $this->attachments = ( $this->fetch )( array_values( array_unique( $ids ) ) ) + array_fill_keys( $ids, null );

        foreach( $this->attachments as $id => $attachment )
        {
            if( $attachment ) {
                $this->add( $attachment['url'] ?? $attachment['guid'], $postIds[$id] ?? [] );
            }
        }

        return $this;
    }


    /**
     * Adds the base URL of the WordPress uploads, the files below are downloaded like the ones below "wp-content/uploads".
     *
     * @param string|null $url Base URL of the uploads from the WordPress options or NULL if not available
     */
    public function uploads( ?string $url ): static
    {
        if( ( $url = rtrim( trim( (string) $url ), '/' ) ) !== '' && !in_array( $url, $this->uploads, true ) ) {
            $this->uploads[] = $url;
        }

        return $this;
    }


    /**
     * Returns the source URLs of the files used by the posts passed to prefetch().
     *
     * @return array<int, string> Rewritten URLs of the attachments and the images without attachment
     */
    public function paths(): array
    {
        return array_values( array_unique( $this->paths ) );
    }


    /**
     * Returns the source URLs of the files used by the posts passed to prefetch() which are downloaded.
     *
     * @param array<int|string>|null $postIds IDs of the posts in the order of their import or NULL for all posts
     * @return array<int, string> Rewritten URLs of the WordPress-managed uploads
     */
    public function downloads( ?array $postIds = null ): array
    {
        if( $postIds === null ) {
            return array_values( array_unique( array_intersect_key( $this->paths, array_filter( $this->managed ) ) ) );
        }

        return array_values( array_unique( array_merge( ...array_map( fn( $postId ) => $this->downloads[$postId] ?? [], $postIds ) ) ) );
    }


    /**
     * Remembers the MIME type of a file, so preview() doesn't need to fetch it.
     *
     * @param string $id ID of the file
     * @param string $mime MIME type of the stored file
     */
    public function remember( string $id, string $mime ): static
    {
        $this->mimes[$id] = $mime;
        $this->mimes = Database::limit( $this->mimes, static::CACHE_SIZE );

        return $this;
    }


    /**
     * Imports the featured image (post thumbnail) for a WordPress post.
     *
     * WordPress shows the alternative text of the attachment for featured images, the title is used if there's none.
     */
    public function featured( int $postId ): ?string
    {
        $thumbnailId = $this->thumbnailIds[$postId] ?? null;

        if( !$thumbnailId || !( $attachment = $this->attachment( $thumbnailId ) ) ) {
            return null;
        }

        return $this->url( $attachment['url'] ?? $attachment['guid'], ( $attachment['alt'] ?? '' ) ?: $attachment['title'], $attachment['mime'] );
    }


    /**
     * Imports an image from an HTML img tag.
     *
     * The original image of the attachment is imported instead of the resized copy used in the content. The
     * attachment is found by the ID in the "wp-image-<ID>" class of the image or by the file name in the source.
     * Tags without source like the ones created for gallery shortcodes are imported by their attachment ID only.
     */
    public function image( string $html ): ?string
    {
        // the HTML entities like "&amp;" in the source and the alternative text are decoded like by the browser
        ['src' => $src, 'alt' => $alt] = Html::decodedSource( $html ) ?? ['src' => '', 'alt' => ''];

        // inline images aren't files and aren't looked up as attachments because they can be very large
        if( $this->inline( $src ) ) {
            return null;
        }

        $id = $this->attachmentId( $html, $src ) ?? ( $src !== '' ? $this->find( $src ) : null );

        if( $id !== null && ( $attachment = $this->attachment( $id ) ) ) {
            // images without alternative text like the ones of gallery shortcodes use the one of the attachment like WordPress
            return $this->url( $attachment['url'] ?? $attachment['guid'], $alt ?: ( $attachment['alt'] ?? '' ) ?: $attachment['title'], $attachment['mime'] );
        }

        return $src !== '' ? $this->url( $src, $alt ) : null;
    }


    /**
     * Tests whether a media URL belongs to WordPress-managed uploads.
     */
    public function managed( string $url ): bool
    {
        $url = html_entity_decode( trim( $url ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $path = parse_url( $url, PHP_URL_PATH );

        if( is_string( $path ) && preg_match( '#(?:^|/)wp-content/uploads(?:/|$)#i', $path ) === 1 ) {
            return true;
        }

        foreach( [...$this->bases, ...$this->uploads] as $base )
        {
            if( $this->matches( $url, $base ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns the ID of the first image file while preserving the content order.
     *
     * @param array<string> $fileIds IDs of the imported files in content order
     */
    public function preview( array $fileIds ): ?string
    {
        // the MIME types of the files of previous imports are fetched once
        if( $missing = array_values( array_diff( array_unique( $fileIds ), array_keys( $this->mimes ) ) ) ) {
            // IDs of failed files aren't UUIDs and PostgreSQL rejects them in UUID columns
            $ids = array_values( array_filter( $missing, fn( $id ) => Str::isUuid( $id ) ) );
            $this->mimes = ( $ids ? File::whereIn( 'id', $ids )->pluck( 'mime', 'id' )->all() : [] ) + array_fill_keys( $missing, '' ) + $this->mimes;
        }

        $found = null;

        foreach( $fileIds as $id )
        {
            if( str_starts_with( $this->mimes[$id] ?? '', 'image/' ) )
            {
                $found = $id;
                break;
            }
        }

        $this->mimes = Database::limit( $this->mimes, static::CACHE_SIZE, count( $missing ) );
        return $found;
    }


    /**
     * Rewrites a WordPress media URL using the configured base URL.
     */
    public function rewrite( string $url ): string
    {
        $base = $this->bases[0] ?? '';

        if( $base === '' ) {
            return $url;
        }

        $path = parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_PATH );

        if( is_string( $path ) && preg_match( '#(?:^|/)wp-content/uploads/(.+)$#i', $path, $m ) ) {
            return $base . '/' . $m[1];
        }

        // uploads at other locations like the "files" URL of old multisite networks are replaced too
        foreach( $this->uploads as $upload )
        {
            $prefix = rtrim( (string) parse_url( $upload, PHP_URL_PATH ), '/' );

            if( is_string( $path ) && str_starts_with( $path, $prefix . '/' ) && $this->matches( $url, $upload ) ) {
                return $base . '/' . substr( $path, strlen( $prefix ) + 1 );
            }
        }

        return $url;
    }


    /**
     * Creates a Pagible File record from a URL, e.g. the guid of a WordPress attachment.
     *
     * @param string $url File URL
     * @param string $name File name, the URL basename if empty
     * @param string $mime MIME type, detected from the URL if empty
     */
    public function url( string $url, string $name = '', string $mime = '' ): ?string
    {
        $name = $name ?: basename( parse_url( $url, PHP_URL_PATH ) ?: 'image' );

        return ( $this->create )( $mime ?: Files::mime( $url ), $name, $this->rewrite( $url ), $this->managed( $url ) );
    }


    /**
     * Adds the source URL of a file used by the posts.
     *
     * @param string $url Source URL of the file
     * @param array<int|string> $postIds IDs of the posts using the file
     */
    protected function add( string $url, array $postIds ): void
    {
        $this->paths[] = $path = $this->rewrite( $url );

        if( $this->managed[] = $this->managed( $url ) )
        {
            foreach( $postIds as $postId ) {
                $this->downloads[$postId][] = $path;
            }
        }
    }


    /**
     * Returns the WordPress image attachment with the given ID.
     *
     * Attachments which weren't fetched for the current posts are fetched one by one.
     *
     * @return array{guid: string, title: string, mime: string, url?: string, alt?: string}|null
     */
    protected function attachment( int|string $id ): ?array
    {
        if( !array_key_exists( $id, $this->attachments ) ) {
            $this->attachments += ( $this->fetch )( [$id] ) + [$id => null];
        }

        return $this->attachments[$id];
    }


    /**
     * Returns the ID of the attachment from the "wp-image-<ID>" class of the image if it's the attachment of the source.
     *
     * The class of copied content can contain the ID of another attachment, so the file name of the source
     * without size suffix must be the one of the attachment.
     *
     * @param string $html HTML image tag, optionally with link or figure around it
     * @param string $src Source of the image or empty if the tag has no source
     */
    protected function attachmentId( string $html, string $src ): int|string|null
    {
        $tag = preg_match( '/<img\b[^>]*>/i', $html, $match ) ? $match[0] : $html;

        if( ( $id = $this->classId( $tag ) ) === null || !( $attachment = $this->attachment( $id ) ) ) {
            return null;
        }

        $basename = $this->basename( $src );
        return $src === '' || $basename === $this->basename( $attachment['guid'] ) || isset( $attachment['url'] ) && $basename === $this->basename( $attachment['url'] ) ? $id : null;
    }


    /**
     * Returns the file name of the URL without the size suffix of resized and scaled images.
     */
    protected function basename( string $url ): string
    {
        $path = parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_PATH );
        return basename( $this->original( is_string( $path ) ? $path : $url ) );
    }


    /**
     * Returns the attachment ID from the "wp-image-<ID>" class WordPress adds to the inserted images.
     *
     * @param string $tag HTML image tag
     */
    protected function classId( string $tag ): ?int
    {
        return preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', Html::attribute( $tag, 'class' ), $match ) && (int) $match[1] > 0 ? (int) $match[1] : null;
    }


    /**
     * Returns the URLs of the files of the file blocks in the post content.
     *
     * @return array<int, string> URLs from the "href" attribute of the file blocks
     */
    protected function fileUrls( string $content ): array
    {
        if( !str_contains( $content, 'wp:file' ) || !preg_match_all( '/<!--\s*wp:(?:core\/)?file\s+(\{(?:(?!-->).)*?\})\s*\/?-->/s', $content, $matches ) ) {
            return [];
        }

        $urls = array_map( fn( $json ) => ( (array) json_decode( $json, true ) )['href'] ?? null, $matches[1] );
        return array_values( array_filter( array_map( fn( $url ) => is_string( $url ) ? trim( $url ) : '', $urls ) ) );
    }


    /**
     * Returns the URL of the original image of a resized or scaled copy, e.g. "photo.jpg" for "photo-300x200.jpg".
     */
    protected function original( string $url ): string
    {
        return (string) preg_replace( static::SIZE_PATTERN, '$1', $url );
    }


    /**
     * Tests if the image source is a data URI containing the image instead of an URL.
     */
    protected function inline( string $src ): bool
    {
        return strncasecmp( ltrim( $src ), 'data:', 5 ) === 0;
    }


    /**
     * Finds the ID of the WordPress attachment matching the given image URL.
     *
     * Sources of resized and scaled copies like "photo-300x200.jpg" are the attachment of the original image
     * if there's no attachment with the name of the copy.
     */
    protected function find( string $src ): int|string|null
    {
        $id = $this->basenameId( $src );
        return $id === null && ( $original = $this->original( $src ) ) !== $src ? $this->basenameId( $original ) : $id;
    }


    /**
     * Finds the ID of the WordPress attachment whose basename is part of the given image URL.
     */
    protected function basenameId( string $src ): int|string|null
    {
        if( array_key_exists( $src, $this->found ) ) {
            return $this->found[$src];
        }

        $this->build();

        // the parts of the URL with the length of a basename are looked up instead of searching each basename in the URL
        $found = null;
        $parts = [];

        foreach( $this->basenameLengths as $length => $true )
        {
            for( $pos = 0, $max = strlen( $src ) - $length; $pos <= $max; $pos++ ) {
                $parts[] = substr( $src, $pos, $length );
            }
        }

        // longer basenames are only looked up where the URL contains their last characters
        for( $end = static::SUFFIX_SIZE, $max = strlen( $src ); $end <= $max; $end++ )
        {
            foreach( $this->suffixLengths[substr( $src, $end - static::SUFFIX_SIZE, static::SUFFIX_SIZE )] ?? [] as $length => $true )
            {
                if( $length <= $end ) {
                    $parts[] = substr( $src, $end - $length, $length );
                }
            }
        }

        foreach( $parts as $part )
        {
            if( ( $pos = $this->basenames[$this->hash( $part )] ?? null ) !== null && ( $found === null || $pos < $found ) ) {
                $found = $pos;
            }
        }

        $this->found[$src] = $found !== null ? $this->ids[$found] : null;
        $this->found = Database::limit( $this->found, static::CACHE_SIZE );

        return $this->found[$src];
    }


    /**
     * Indexes the basenames of the attachments when an image URL is looked up for the first time.
     *
     * The basenames are indexed by their 64 bit hash which needs less than half of the memory of the
     * basenames themselves, so the attachments of large media libraries can be found in image URLs too.
     */
    protected function build(): void
    {
        if( $this->attachmentIds === null ) {
            return;
        }

        foreach( $this->attachmentIds as $basename => $id )
        {
            $hash = $this->hash( (string) $basename );

            // attachments with the same basename keep the position of the first one
            if( isset( $this->basenames[$hash] ) )
            {
                $this->ids[$this->basenames[$hash]] = $id;
                continue;
            }

            $this->basenames[$hash] = count( $this->ids );

            if( ( $length = strlen( (string) $basename ) ) < static::SUFFIX_SIZE ) {
                $this->basenameLengths[$length] = true;
            } else {
                $this->suffixLengths[substr( (string) $basename, $length - static::SUFFIX_SIZE )][$length] = true;
            }
            $this->ids[] = $id;
        }

        $this->attachmentIds = null;
    }


    /**
     * Returns the 64 bit hash of the basename used as key of the basename index.
     */
    protected function hash( string $basename ): int
    {
        $value = unpack( 'J', hash( 'xxh64', $basename, true ) );

        return is_array( $value ) && is_int( $value[1] ?? null ) ? $value[1] : 0;
    }


    /**
     * Tests whether a URL is located at or below a configured media base URL.
     */
    protected function matches( string $url, string $base ): bool
    {
        $candidate = parse_url( $url );
        $configured = parse_url( $base );

        if( !is_array( $candidate ) || !is_array( $configured ) ) {
            return false;
        }

        $scheme = strtolower( (string) ( $candidate['scheme'] ?? '' ) );
        $baseScheme = strtolower( (string) ( $configured['scheme'] ?? '' ) );
        $host = strtolower( (string) ( $candidate['host'] ?? '' ) );
        $baseHost = strtolower( (string) ( $configured['host'] ?? '' ) );

        if( !in_array( $scheme, ['http', 'https'], true ) || $scheme !== $baseScheme || $host === '' || $host !== $baseHost ) {
            return false;
        }

        $port = (int) ( $candidate['port'] ?? ( $scheme === 'https' ? 443 : 80 ) );
        $basePort = (int) ( $configured['port'] ?? ( $baseScheme === 'https' ? 443 : 80 ) );

        if( $port !== $basePort ) {
            return false;
        }

        $path = '/' . ltrim( (string) ( $candidate['path'] ?? '/' ), '/' );
        $basePath = rtrim( '/' . ltrim( (string) ( $configured['path'] ?? '/' ), '/' ), '/' ) ?: '/';

        return $basePath === '/' || $path === $basePath || str_starts_with( $path, $basePath . '/' );
    }
}
