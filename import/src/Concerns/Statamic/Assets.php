<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Statamic\Content;


/**
 * Imports the Statamic assets used by the entries as Pagible files.
 */
trait Assets
{
    /** @var array<string, string> Base URLs of the assets by container handle */
    protected array $fileBases = [];


    /**
     * Returns the URL of the asset using the base URL of its container.
     *
     * @param string $asset Asset like "container::path", absolute path or URL
     * @return string URL of the asset, relative if there's no base URL for its container
     */
    protected function fileUrl( string $asset ): string
    {
        if( preg_match( '/^([^:\/]+)::(.+)$/s', $asset, $match ) ) {
            return Files::url( $match[2], $this->fileBases[$match[1]] ?? $this->fileBase );
        }

        // absolute paths are relative to the host of the base URL
        if( str_starts_with( $asset, '/' ) && !str_starts_with( $asset, '//' ) && preg_match( '#^[a-z][a-z0-9+.-]*://[^/]+#i', $this->fileBase, $match ) ) {
            return $match[0] . $asset;
        }

        return Files::url( $asset, $this->fileBase );
    }


    /**
     * Imports the asset as file or returns the file of a previous import.
     *
     * @param string $asset Asset like "container::path", absolute path or URL
     * @param string $alt Alternative text used as name of new files
     * @return string|null File ID or NULL if there's no asset
     */
    protected function importAsset( string $asset, string $alt = '' ): ?string
    {
        if( ( $url = $this->fileUrl( $asset ) ) === '' ) {
            return null;
        }

        // assets without base URL can't be downloaded and would be stored with a broken path
        if( !preg_match( '#^https?://#i', $url ) )
        {
            $container = preg_match( '/^([^:\/]+)::/', $asset, $match ) ? $match[1] : '';
            $this->warnOnce( "file-base:{$container}", '  Assets ' . ( $container !== '' ? "of the \"{$container}\" container " : '' )
                . 'aren\'t imported because their URLs are unknown, use --file-base=' . ( $container !== '' ? "{$container}=" : '' ) . '<URL> to import them' );
            return null;
        }

        // the name and type of the asset are only used for new files
        if( $id = $this->knownFile( $url ) ) {
            return $id;
        }

        $info = preg_match( '/^([^:\/]+)::(.+)$/s', $asset, $match ) ? $this->reader->asset( $match[1], $match[2] ) : null;
        $name = trim( $alt ) ?: ( $info['name'] ?? '' ) ?: pathinfo( (string) ( parse_url( $url, PHP_URL_PATH ) ?: $url ), PATHINFO_FILENAME );
        $ext = $info['extension'] ?? '';
        // the MIME type stored by Statamic is used if the extension is unknown
        $mime = Files::mime( $ext !== '' ? 'file.' . $ext : $url );
        $mime = $mime === 'application/octet-stream' && ( $info['mime'] ?? '' ) !== '' ? $info['mime'] : $mime;

        // the alt text of the asset is the description of new files
        return $this->createFile( $mime, $name, $url, description: ( $info['alt'] ?? '' ) !== '' ? [$this->lang => $info['alt']] : [] );
    }


    /**
     * Imports the featured image of the entry.
     *
     * The fields named like featured images are used first, then the asset fields with a single image of the
     * blueprint which aren't social media images.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param array<int, string> $fields Handles of the asset fields with a single asset in the blueprint
     * @return string|null File ID or NULL if the entry has no featured image
     */
    protected function featuredImage( array $data, array $fields = [] ): ?string
    {
        $fields = array_diff( $fields, static::IMAGE_FIELDS, static::META_IMAGE_FIELDS );

        foreach( array_merge( static::IMAGE_FIELDS, $fields ) as $field )
        {
            $value = $data[$field] ?? null;
            $value = is_array( $value ) ? reset( $value ) : $value;

            if( !is_string( $value ) || !in_array( $field, static::IMAGE_FIELDS, true ) && !preg_match( static::IMAGE_PATTERN, $value ) ) {
                continue;
            }

            if( ( $asset = Content::asset( $value, $this->content->container( $field ) ) ) !== '' ) {
                return $this->importAsset( $asset );
            }
        }

        return null;
    }
}
