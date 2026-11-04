<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads the published posts, image attachments and featured images from a WordPress database.
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
 * }
 */
class WpReader
{
    /** @var int Number of rows fetched by one query */
    protected const CHUNK_SIZE = 100;
    /** @var array<int, string> Columns of the posts which are used */
    protected const POST_COLUMNS = ['ID', 'post_name', 'post_title', 'post_excerpt', 'post_content', 'post_date_gmt', 'post_date', 'post_modified_gmt', 'post_password'];
    /** @var array<int, string> Columns of the posts which contain the texts */
    protected const TEXT_COLUMNS = ['post_excerpt', 'post_content'];
    /** @var int Number of attachment IDs and URLs fetched by one query */
    protected const BASENAME_CHUNK_SIZE = 5000;

    /**
     * @param Connection $db Connection to the WordPress database
     */
    public function __construct( protected Connection $db )
    {
    }


    /**
     * Fetches the WordPress image attachments with the given IDs.
     *
     * @param array<int|string> $ids IDs of the attachments, e.g. of the images used by one chunk of posts
     * @return array<int|string, array{guid: string, title: string, mime: string}> Attachments by ID
     */
    public function attachments( array $ids ): array
    {
        $attachments = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            foreach( $this->images()->whereIn( 'ID', $chunk )->get( ['ID', 'guid', 'post_title', 'post_mime_type'] ) as $attachment ) {
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
     * Returns the number of published posts.
     */
    public function count(): int
    {
        return $this->posts()->count();
    }


    /**
     * Returns the rows of the published posts one by one.
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

        if( is_string( $id ) && ctype_digit( $id ) ) {
            $id = filter_var( $id, FILTER_VALIDATE_INT );
        }

        if( !is_int( $id ) || !is_string( $name ) || !is_string( $title ) || !is_string( $excerpt )
            || !is_string( $content ) || !( is_string( $dateGmt ) || $dateGmt === null ) || !is_string( $date )
            || !( is_string( $modified ) || $modified === null ) || !is_string( $password ) ) {
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
        ];
    }


    /**
     * Fetches the attachment IDs of the featured images (post thumbnails) of the posts.
     *
     * @param array<int, int> $postIds IDs of the posts of one chunk
     * @return array<int|string, int|string> Attachment IDs by post ID
     */
    public function thumbnails( array $postIds ): array
    {
        return $this->db->table( 'wp_postmeta' )
            ->where( 'meta_key', '_thumbnail_id' )
            ->whereIn( 'post_id', $postIds )
            ->pluck( 'meta_value', 'post_id' )
            ->all();
    }


    /**
     * Validates a WordPress attachment database row.
     *
     * @return array{guid: string, title: string, mime: string}
     */
    protected function attachment( object $attachment ): array
    {
        $data = get_object_vars( $attachment );
        $guid = $data['guid'] ?? null;
        $title = $data['post_title'] ?? null;
        $mime = $data['post_mime_type'] ?? null;

        if( !is_string( $guid ) || !is_string( $title ) || !is_string( $mime ) ) {
            throw new \UnexpectedValueException( 'Invalid WordPress attachment row.' );
        }

        return ['guid' => $guid, 'title' => $title, 'mime' => $mime];
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
     * Returns the query for the image attachments.
     */
    protected function images(): Builder
    {
        return $this->db->table( 'wp_posts' )
            ->where( 'post_type', 'attachment' )
            ->whereIn( 'post_mime_type', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'] );
    }


    /**
     * Returns the query for the published posts ordered by date and ID for posts of the same date.
     */
    /**
     * Adds the texts to the post rows, the texts of the rows which aren't required are empty.
     *
     * @param array<int, \stdClass> $rows Post rows without texts
     * @param (\Closure(object): bool)|null $required Returns TRUE for the post rows whose texts are required or NULL for all rows
     */
    public function texts( array $rows, ?\Closure $required = null ): void
    {
        $ids = array_map( fn( $row ) => get_object_vars( $row )['ID'] ?? null, $required ? array_filter( $rows, $required ) : $rows );
        $texts = $ids ? $this->db->table( 'wp_posts' )->whereIn( 'ID', array_values( $ids ) )->get( ['ID', ...self::TEXT_COLUMNS] )->keyBy( 'ID' ) : null;

        foreach( $rows as $row )
        {
            $text = get_object_vars( $texts?->get( get_object_vars( $row )['ID'] ?? '' ) ?? new \stdClass );

            foreach( self::TEXT_COLUMNS as $column ) {
                $row->{$column} = $text[$column] ?? '';
            }
        }
    }


    protected function posts(): Builder
    {
        return $this->db->table( 'wp_posts' )
            ->where( 'post_type', 'post' )
            ->where( 'post_status', 'publish' )
            ->orderBy( 'post_date', 'asc' )
            ->orderBy( 'ID', 'asc' );
    }
}
