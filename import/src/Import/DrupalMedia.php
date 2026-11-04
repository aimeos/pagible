<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;


/**
 * Renders the Drupal media entities, files and videos as content elements.
 */
class DrupalMedia
{
    /** @var array<string, array{source: string, source_field: string}> Media types of Drupal's standard profile used without config table */
    protected array $mediaTypes = [
        'audio' => ['source' => 'audio_file', 'source_field' => 'field_media_audio_file'],
        'document' => ['source' => 'file', 'source_field' => 'field_media_document'],
        'image' => ['source' => 'image', 'source_field' => 'field_media_image'],
        'remote_video' => ['source' => 'oembed:video', 'source_field' => 'field_media_oembed_video'],
        'video' => ['source' => 'video_file', 'source_field' => 'field_media_video_file'],
    ];


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Configuration, entities and field values of the Drupal database
     * @param DrupalFiles $files Imports the files of the Drupal site
     * @param DrupalConverters $converters Converts the HTML to content elements
     * @param \Closure(string, string): void $warnOnce Shows the warning of the key only once
     */
    public function __construct(
        protected DrupalData $data,
        protected DrupalFiles $files,
        protected DrupalConverters $converters,
        protected \Closure $warnOnce
    ) {
    }


    /**
     * Fetches the media, nodes and files embedded into the formatted texts of the field rows and the linked files at once.
     *
     * @param array<int|string, iterable<int, \stdClass>> $rows Field rows by entity ID
     * @param string $field Field name, e.g. "body"
     */
    public function prefetch( array $rows, string $field ): void
    {
        $uuids = $ids = ['media' => [], 'node' => [], 'file' => []];
        $uris = $tids = [];

        foreach( $rows as $list )
        {
            foreach( $list as $row )
            {
                $text = ( $row->{$field . '_value'} ?? '' ) . ( $row->{$field . '_summary'} ?? '' );

                // the files of the Drupal site are linked by their URLs
                if( preg_match_all( '#\shref=["\'][^"\']*/(sites/[^/"\']+/files|system/files)/([^"\'?\#]+)#i', $text, $matches, PREG_SET_ORDER ) )
                {
                    foreach( $matches as $match ) {
                        $uris[] = ( str_starts_with( strtolower( $match[1] ), 'system' ) ? 'private://' : 'public://' ) . rawurldecode( $match[2] );
                    }
                }

                // the URLs of the linked terms require their records
                if( preg_match_all( '#\shref=["\'][^"\']*/taxonomy/term/(\d+)(?=["\'?\#])#i', $text, $matches ) ) {
                    array_push( $tids, ...array_map( 'intval', $matches[1] ) );
                }

                if( !preg_match_all( '/<drupal-(media|entity)\b[^>]*>/i', $text, $matches, PREG_SET_ORDER ) ) {
                    continue;
                }

                foreach( $matches as $match )
                {
                    $type = strtolower( $match[1] ) === 'media' ? 'media' : DrupalHtml::attribute( $match[0], 'data-entity-type' );

                    if( !isset( $uuids[$type] ) ) {
                        continue;
                    }

                    if( ( $uuid = DrupalHtml::attribute( $match[0], 'data-entity-uuid' ) ) !== '' ) {
                        $uuids[$type][] = $uuid;
                    } elseif( ctype_digit( $id = DrupalHtml::attribute( $match[0], 'data-entity-id' ) ) ) {
                        $ids[$type][] = (int) $id;
                    }
                }
            }
        }

        if( $uuids['media'] || $ids['media'] ) {
            $this->data->prefetchMedia( $uuids['media'], $ids['media'] );
        }

        $this->data->prefetchEmbeds( $uuids['node'], $ids['node'], $uuids['file'], $ids['file'], $uris );
        $this->data->prefetch( 'taxonomy_term_field_data', 'tid', $tids );
    }


    /**
     * Fetches the terms linked by the link field rows at once.
     *
     * @param array<int|string, iterable<int, \stdClass>> $rows Field rows by entity ID
     * @param string $field Field name, e.g. "field_link"
     */
    public function prefetchLinks( array $rows, string $field ): void
    {
        $tids = [];

        foreach( $rows as $list )
        {
            foreach( $list as $row )
            {
                if( preg_match( '/^(?:entity:taxonomy_term\/|internal:\/taxonomy\/term\/)(\d+)(?:[?#]|$)/', (string) ( $row->{$field . '_uri'} ?? '' ), $match ) ) {
                    $tids[] = (int) $match[1];
                }
            }
        }

        $this->data->prefetch( 'taxonomy_term_field_data', 'tid', $tids );
    }


    /**
     * Returns the content of a media entity.
     *
     * Images become image elements, local audio, video and other files the matching elements
     * and remote videos (oEmbed) links to the video.
     *
     * @param \stdClass|null $media Media record from the media table
     * @param string $langcode Drupal language code
     * @param string $alt Alternative text overriding the one of the media image
     * @param string $caption Caption shown next to the image
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    public function render( ?\stdClass $media, string $langcode, string $alt = '', string $caption = '' ): ?array
    {
        $bundle = (string) ( $media->bundle ?? '' );
        $type = $this->mediaType( $bundle );

        if( !$media || !$type || !$this->data->hasTable( $this->data->fieldTable( 'media', $type['source_field'] ) ) )
        {
            ( $this->warnOnce )( "media:{$bundle}", $media ? "  Unsupported Drupal media type \"{$bundle}\"" : '  Skipped embedded media (not found)' );
            return null;
        }

        $field = $type['source_field'];
        $name = trim( (string) ( $this->data->entity( 'media_field_data', 'mid', (int) $media->mid, $langcode )->name ?? '' ) );
        $row = $this->data->fieldValues( 'media', $field, (int) $media->mid, $langcode, fallback: true )->first();
        $this->data->prefetchTargets( $this->data->fieldTable( 'media', $field ), $field, 'file' );

        if( !empty( $url = $row->{$field . '_value'} ?? null ) && filter_var( $url, FILTER_VALIDATE_URL ) )
        {
            // YouTube and Vimeo videos are embedded, other remote media are linked
            if( $html = Embeds::iframe( Embeds::player( (string) $url ), $alt ?: $name ) ) {
                return ['elements' => [Elements::create( 'html', ['text' => $html] )], 'fileIds' => []];
            }

            $link = '<a href="' . e( $url ) . '">' . e( $alt ?: $name ?: $url ) . '</a>';
            return ['elements' => [Elements::create( 'html', ['text' => Utils::html( '<p>' . $link . '</p>' )] )], 'fileIds' => []];
        }

        // the media name is used for files without alternative text, e.g. documents
        if( !( $fileId = $this->files->import( (int) ( $row->{$field . '_target_id'} ?? 0 ), $alt ?: (string) ( $row->{$field . '_alt'} ?? '' ) ?: $name ) ) ) {
            return null;
        }

        $element = match( $type['source'] ) {
            'image' => null,
            'audio_file' => 'audio',
            'video_file' => 'video',
            default => 'file',
        };

        if( $element === null ) {
            return Elements::imageContent( $fileId, $caption );
        }

        return ['elements' => [Elements::create( $element, ['file' => Elements::file( $fileId )] )], 'fileIds' => [$fileId]];
    }


    /**
     * Returns the content of a file, remote videos of the Drupal 7 Media module become players.
     *
     * @param int $fid Drupal file ID
     * @param string $name Name of the file, e.g. the description
     * @param string $langcode Drupal language code
     * @param bool $players TRUE to add audio and video files as audio and video elements
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    public function file( int $fid, string $name, string $langcode, bool $players = false ): ?array
    {
        if( $video = DrupalHtml::streamUrl( (string) ( $this->data->fileRow( $fid )->uri ?? '' ) ) ) {
            return $this->video( $video, $langcode );
        }

        if( !( $fileId = $this->files->import( $fid, $name ) ) ) {
            return null;
        }

        $type = strstr( (string) ( $this->data->fileRow( $fid )->filemime ?? '' ), '/', true );
        $type = $players && in_array( $type, ['audio', 'video'], true ) ? $type : 'file';

        return ['elements' => [Elements::create( $type, ['file' => Elements::file( $fileId )] )], 'fileIds' => [$fileId]];
    }


    /**
     * Returns the player of a YouTube or Vimeo video or a link to other videos.
     *
     * @param string $url URL of the video page
     * @param string $langcode Drupal language code
     * @return array{elements: array<int, array<string, mixed>>, fileIds?: array<int, string>}|null
     */
    public function video( string $url, string $langcode ): ?array
    {
        if( $html = Embeds::iframe( Embeds::player( $url ) ) ) {
            return ['elements' => [Elements::create( 'html', ['text' => $html] )], 'fileIds' => []];
        }

        return preg_match( '#^https?://#i', $url ) ? $this->converters->html( $langcode )->text( '<p><a href="' . e( $url ) . '">' . e( $url ) . '</a></p>' ) : null;
    }


    /**
     * Returns the source plugin and source field of the Drupal media type.
     *
     * The source field is configured per media type ("source_configuration.source_field"),
     * the media types of Drupal's standard profile are used if the config table isn't available.
     *
     * @return array{source: string, source_field: string}|null
     */
    protected function mediaType( string $bundle ): ?array
    {
        if( !$this->data->hasTable( 'config' ) ) {
            return $this->mediaTypes[$bundle] ?? null;
        }

        $config = $this->data->config( 'media.type.' . $bundle );

        if( empty( $field = $config['source_configuration']['source_field'] ?? null ) ) {
            return null;
        }

        return ['source' => (string) ( $config['source'] ?? '' ), 'source_field' => (string) $field];
    }
}
