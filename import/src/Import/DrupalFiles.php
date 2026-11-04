<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Imports the files of the Drupal site referenced by fields, HTML and meta tags.
 */
class DrupalFiles
{
    /** TRUE if the warning about private files was shown */
    protected bool $privateWarned = false;


    /**
     * Initializes the object.
     *
     * @param DrupalData $data Files of the Drupal database
     * @param DrupalSite $site URLs of the Drupal site
     * @param \Closure(string, string, string, bool=): string $create Creates the file by MIME type, name, URL and TRUE to rename existing files, returns the file ID
     * @param \Closure(string): void $warn Shows a warning
     * @param (\Closure(array<int, string>): void)|null $prefetch Looks up the existing files by their URLs at once
     */
    public function __construct( protected DrupalData $data, protected DrupalSite $site, protected \Closure $create, protected \Closure $warn,
        protected ?\Closure $prefetch = null )
    {
    }


    /**
     * Imports a Drupal file_managed record into a Pagible File.
     */
    public function import( int $fid, string $alt = '' ): ?string
    {
        if( !( $file = $this->data->fileRow( $fid ) ) ) {
            return null;
        }

        $filename = (string) ( $file->filename ?? '' );
        $mime = (string) ( $file->filemime ?? '' ) ?: $this->mime( pathinfo( $filename, PATHINFO_EXTENSION ) );

        // the files of the records fetched together are looked up together
        if( $this->prefetch && ( $uris = $this->data->fetchedUris() ) ) {
            ( $this->prefetch )( array_map( fn( $uri ) => $this->url( $uri ), $uris ) );
        }

        return ( $this->create )( $mime, $alt ?: $filename, $this->url( (string) ( $file->uri ?? '' ) ) );
    }


    /**
     * Returns the URL of a Drupal file URI.
     *
     * Private files are served by Drupal from /system/files/ of the site instead of the public files directory.
     */
    public function url( string $uri ): string
    {
        if( str_starts_with( $uri, 'private://' ) && ( $base = $this->site->base() ) )
        {
            if( !$this->privateWarned ) {
                ( $this->warn )( '  Private Drupal files are downloaded from /system/files/ and fail if they require a login' );
                $this->privateWarned = true;
            }

            return $base . '/system/files/' . substr( $uri, 10 );
        }

        return $this->site->file( (string) preg_replace( '/^(public|private):\/\//', '', $uri ) );
    }


    /**
     * Imports the image of the HTML tag from the Drupal site.
     *
     * @param string $tag HTML image tag
     * @return string|null File ID or NULL if the tag has no source
     */
    public function image( string $tag ): ?string
    {
        if( !( $image = Html::source( $tag ) ) ) {
            return null;
        }

        $name = $image['alt'] ?: basename( parse_url( $image['src'], PHP_URL_PATH ) ?: 'image' );
        $mime = $this->mime( pathinfo( $name, PATHINFO_EXTENSION ) );

        return ( $this->create )( $mime, $name, $this->site->image( $image['src'] ) );
    }


    /**
     * Returns the content of a paragraph which only contains a link to a file of the Drupal site.
     *
     * Such links become file elements, or image elements for images, so the files are managed in
     * Pagible. Links to files within text stay links to the files of the Drupal site.
     *
     * @param string $url URL of the link
     * @param string $text Link text used as file name
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    public function linked( string $url, string $text ): ?array
    {
        $base = $this->site->base();

        if( $base === '' || !str_starts_with( $url, $base . '/' )
            || !preg_match( '#^/(?:sites/[^/]+/files|system/files)/([^?\#]+)$#i', substr( $url, strlen( $base ) ), $m )
        ) {
            return null;
        }

        $scheme = str_starts_with( substr( $url, strlen( $base ) ), '/system/' ) ? 'private://' : 'public://';
        $file = $this->data->entity( 'file_managed', 'uri', $scheme . rawurldecode( $m[1] ) );
        $mime = (string) ( $file->filemime ?? '' ) ?: $this->mime( pathinfo( $m[1], PATHINFO_EXTENSION ) );

        $fileId = $file
            ? $this->import( (int) $file->fid, $text )
            : ( $this->create )( $mime, $text ?: rawurldecode( basename( $m[1] ) ), $url );

        return $fileId ? self::content( $fileId, $mime ) : null;
    }


    /**
     * Imports the file of a video or audio tag.
     *
     * @param string $type "video" or "audio"
     * @param string $src URL or path of the file
     * @return string|null File ID or NULL if the file can't be imported
     */
    public function player( string $type, string $src ): ?string
    {
        $url = $this->site->image( $src );
        $name = rawurldecode( basename( (string) parse_url( $url, PHP_URL_PATH ) ) ) ?: $type;
        $mime = $this->mime( pathinfo( $name, PATHINFO_EXTENSION ) );

        return ( $this->create )( str_starts_with( $mime, $type . '/' ) ? $mime : $type . '/mp4', $name, $url ) ?: null;
    }


    /**
     * Imports the image of the Open Graph meta tag.
     *
     * Files imported before keep their name because it's usually the alternative text of the image.
     *
     * @param string $urls Image URL or comma separated list of URLs whose first one is used
     * @return string|null File ID or NULL if it's not an image
     */
    public function meta( string $urls ): ?string
    {
        $src = $this->site->image( trim( explode( ',', $urls )[0] ) );
        $path = (string) parse_url( $src, PHP_URL_PATH );
        $mime = $this->mime( pathinfo( $path, PATHINFO_EXTENSION ) );

        if( $path === '' || !str_starts_with( $mime, 'image/' ) ) {
            return null;
        }

        return ( $this->create )( $mime, basename( $path ), $src, false ) ?: null;
    }


    /**
     * Returns the MIME type of the file extension.
     */
    protected function mime( string $ext ): string
    {
        return Files::mime( 'file.' . $ext );
    }


    /**
     * Returns an image element for images and a file element for other files.
     *
     * @param string $fileId ID of the file
     * @param string $mime MIME type of the file
     * @param string $caption Caption of images
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>}|null
     */
    public static function content( string $fileId, string $mime, string $caption = '' ): ?array
    {
        if( str_starts_with( $mime, 'image/' ) ) {
            return Elements::imageContent( $fileId, $caption );
        }

        return ['elements' => [Elements::create( 'file', ['file' => Elements::file( $fileId )] )], 'fileIds' => [$fileId]];
    }
}
