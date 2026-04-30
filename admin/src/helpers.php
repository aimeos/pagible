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
