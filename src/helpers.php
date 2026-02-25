<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


if( !function_exists( 'cms' ) )
{
    /**
     * Access nested properties of an item, with support for latest version if the user has permission to
     *
     * @param object|null $item The item to access
     * @param string|null $prop The property path to access, e.g. "title"
     * @param mixed $default The default value to return if the property is not found
     * @return mixed The value of the property or the default value
     */
    function cms( ?object $item, ?string $prop, mixed $default = null ) : mixed
    {
        if( is_null( $item ) || is_null( $prop ) ) {
            return $default;
        }

        $parts = explode( '.', $prop );
        $first = array_shift( $parts );

        if( $item instanceof \Illuminate\Support\Collection ) {
            $val = $item->get( $first );
        }
        else if( \Aimeos\Cms\Permission::can( 'page:view', \Illuminate\Support\Facades\Auth::user() ) ) {
            $val = @$item->latest?->data?->{$first} // @phpstan-ignore-line property.notFound
                ?? @$item->latest?->aux?->{$first} // @phpstan-ignore-line property.notFound
                ?? @$item->latest?->{$first} // @phpstan-ignore-line property.notFound
                ?? @$item->{$first};
        }
        else {
            $val = @$item->{$first};
        }

        foreach( $parts as $part )
        {
            if( is_object( $val ) && ( $val = @$val->{$part} ) === null ) {
                return $default;
            }
        }

        return $val ?? $default;
    }
}


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


if( !function_exists( 'cmsattr' ) )
{
    /**
     * Sanitize a string to be used as an HTML attribute by replacing non-alphanumeric characters with hyphens.
     *
     * @param string|null $name The input string to sanitize
     * @return string The sanitized string suitable for use as an HTML attribute
     */
    function cmsattr( ?string $name ) : string
    {
        return (string) preg_replace('/[^A-Za-z0-9\-\_]+/', '-', (string) $name);
    }
}


if( !function_exists( 'cmsdata' ) )
{
    /**
     * Get the data for a CMS element.
     *
     * @param \Aimeos\Cms\Models\Page $page The CMS page
     * @param object $item The CMS element
     * @return array<string, mixed> The data for the CMS element
     */
    function cmsdata( \Aimeos\Cms\Models\Page $page, object $item ) : array
    {
        if( $item instanceof \Aimeos\Cms\Models\Element ) {
            $item = (object) $item->toArray();
        }

        $data = ['files' => cms($page, 'files')];

        /** @phpstan-ignore property.notFound */
        if( $action = @$item->data?->action ) {
            $data['action'] = app()->call( $action, ['page' => $page, 'item' => $item] );
        }

        return $data + (array) $item;
    }
}


if( !function_exists( 'cmsfile' ) )
{
    /**
     * Get a file from the CMS page by its ID.
     *
     * @param \Aimeos\Cms\Models\Page $page The CMS page
     * @param string $fileId The ID of the file to retrieve
     * @return object|null The file object if found, or null if not found
     */
    function cmsfile( \Aimeos\Cms\Models\Page $page, string $fileId ) : ?object
    {
        return cms( cms( $page, 'files' ), $fileId );
    }
}


if( !function_exists( 'cmsref' ) )
{
    /**
     * Resolve a reference item to its actual element if the user has permission to view it.
     *
     * @param \Aimeos\Cms\Models\Page $page The CMS page
     * @param object $item The item to resolve, which may be a reference
     * @return object The resolved item if it was a reference and the user has permission,
     *  or the original item if it was not a reference or the user does not have permission
     */
    function cmsref( \Aimeos\Cms\Models\Page $page, object $item ) : object
    {
        // @phpstan-ignore-next-line property.notFound
        if(@$item->type === 'reference' && ($refid = @$item->refid) && ($element = cms(cms($page, 'elements'), $refid))) {
            return (object) $element;
        }

        return $item;
    }
}


if( !function_exists( 'cmsroute' ) )
{
    /**
     * Generate a route for a CMS page, using the latest version if the user has permission to view it.
     *
     * @param \Aimeos\Cms\Models\Page $page The CMS page for which to generate the route
     * @return string The generated route URL for the page
     */
    function cmsroute( \Aimeos\Cms\Models\Page $page ) : string
    {
        if( \Aimeos\Cms\Permission::can( 'page:view', \Illuminate\Support\Facades\Auth::user() ) ) {
            return @$page->latest?->data?->to ?: route( 'cms.page', ['path' => @$page->latest?->data?->path ?? @$page->path] );
        }

        return @$page->to ?: route( 'cms.page', ['path' => @$page->path] );
    }
}


if( !function_exists( 'cmssrcset' ) )
{
    /**
     * Generate a srcset attribute value for responsive images from an associative array of widths and paths.
     *
     * @param array<int, string> $data An associative array where the key is the width (e.g. "300") and the value is the image path
     * @return string A srcset string that can be used in an HTML img tag, e.g. "image-300.jpg 300w, image-600.jpg 600w"
     */
    function cmssrcset( mixed $data ) : string
    {
        $list = [];

        foreach( (array) $data as $width => $path ) {
            $list[] = cmsurl( $path ) . ' ' . $width . 'w';
        }

        return implode( ',', $list );
    }
}


if( !function_exists( 'cmsurl' ) )
{
    /**
     * Generate a URL for a CMS file, handling both external URLs and local storage paths.
     *
     * @param string|null $path The path to the file, which can be an external URL or a local storage path
     * @return string The generated URL for the file, or an empty string if the path contains no value
     */
    function cmsurl( ?string $path ) : string
    {
        if( !$path ) {
            return '';
        }

        if( \Illuminate\Support\Str::startsWith( $path, ['data:', 'http:', 'https:'] ) ) {
            return $path;
        }

        return \Illuminate\Support\Facades\Storage::disk( config( 'cms.disk', 'public' ) )->url( $path );
    }
}


if( !function_exists( 'cmsviews' ) )
{
    /**
     * Get the list of view names to try for a CMS element, based on its type and the page theme.
     *
     * @param \Aimeos\Cms\Models\Page $page The CMS page
     * @param object $item The CMS element for which to get the view names
     * @return array<int, string> An array of view names to try when rendering the element
     */
    function cmsviews( \Aimeos\Cms\Models\Page $page, object $item ) : array
    {
        return isset( $item->type ) ? [
            $item->type,
            (cms($page, 'theme') ?: 'cms') . '::' . $item->type,
            'cms::invalid'
        ] : ['cms::invalid'];
    }
}
