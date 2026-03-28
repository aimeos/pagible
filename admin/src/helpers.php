<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


if( !function_exists( 'cmsadmin' ) )
{
    /**
     * Access the CMS admin manifest for the given path.
     *
     * @param string $path The path to the manifest file, e.g. "admin/manifest.json"
     * @return array<string, mixed> The manifest data or an empty array if not found
     */
    function cmsadmin( string $path ) : array
    {
        $manifest = json_decode( file_get_contents( public_path( $path ) ) ?: '{}', true ) ?? [];
        return $manifest['index.html'] ?? [];
    }
}


if( !function_exists( 'cmsasset' ) )
{
    /**
     * Generate an asset URL with a version query parameter based on the file's last modification time for cache busting.
     *
     * @param string|null $path The path to the asset file
     * @return string The asset URL with a version query parameter, or an empty string if the path is null
     */
    function cmsasset( ?string $path ) : string
    {
        return $path ? asset( $path ) . '?v=' . ( file_exists( public_path( $path ) ) ? filemtime( public_path( $path ) ) : 0 ) : '';
    }
}
