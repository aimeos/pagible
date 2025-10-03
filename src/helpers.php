<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


if( !function_exists( 'cms' ) )
{
    function cms( ?object $item, ?string $prop, $default = null )
    {
        if( is_null( $item ) || is_null( $prop ) ) {
            return $default;
        }

        $parts = explode( '.', $prop );
        $first = array_shift( $parts );

        if( $item instanceof \Illuminate\Support\Collection ) {
            $val = $item->get( $first );
        }
        else if( \Aimeos\Cms\Permission::can( 'page:view', auth()->user() ) ) {
            $val = @$item->latest?->data?->{$first}
                ?? @$item->latest?->aux?->{$first}
                ?? @$item->latest?->{$first}
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
    function cmsadmin( string $path ): array
    {
        $manifest = file_exists( public_path( $path ) ) ? json_decode( file_get_contents( public_path( $path ) ), true ) : [];
        return $manifest['index.html'] ?? [];
    }
}


if( !function_exists( 'cmsasset' ) )
{
    function cmsasset( ?string $path ): string
    {
        return $path ? asset( $path ) . '?v=' . ( file_exists( public_path( $path ) ) ? filemtime( public_path( $path ) ) : 0 ) : '';
    }
}


if( !function_exists( 'cmsattr' ) )
{
    function cmsattr( ?string $name ): string
    {
        return $name ? preg_replace('/[^A-Za-z0-9\-\_]+/', '-', $name) : '';
    }
}


if( !function_exists( 'cmsdata' ) )
{
    function cmsdata( \Aimeos\Cms\Models\Page $page, object $item ): array
    {
        if( $item instanceof \Aimeos\Cms\Models\Element ) {
            $item = (object) $item->toArray();
        }

        $data = ['files' => cms($page, 'files')];

        if( $action = @$item->data?->action ) {
            $data['action'] = app()->call( $action, ['page' => $page, 'item' => $item] );
        }

        return $data + (array) $item;
    }
}


if( !function_exists( 'cmsfile' ) )
{
    function cmsfile( \Aimeos\Cms\Models\Page $page, string $fileId ): object
    {
        return cms( cms( $page, 'files' ), $fileId );
    }
}


if( !function_exists( 'cmsref' ) )
{
    function cmsref( \Aimeos\Cms\Models\Page $page, object $item ): object
    {
        if(@$item->type === 'reference' && ($refid = @$item->refid) && ($element = cms(cms($page, 'elements'), $refid))) {
            return (object) $element;
        }

        return $item;
    }
}


if( !function_exists( 'cmsroute' ) )
{
    function cmsroute( \Aimeos\Cms\Models\Page $page ): string
    {
        if( \Aimeos\Cms\Permission::can( 'page:view', auth()->user() ) ) {
            return @$page->latest?->data?->to ?: route( 'cms.page', ['path' => @$page->latest?->data?->path ?? @$page?->path] );
        }

        return @$page->to ?: route( 'cms.page', ['path' => @$page->path] );
    }
}


if( !function_exists( 'cmssrcset' ) )
{
    function cmssrcset( $data ): string
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
    function cmsurl( ?string $path ): string
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
    function cmsviews( \Aimeos\Cms\Models\Page $page, object $item ): array
    {
        return isset( $item->type ) ? [
            $item->type,
            (cms($page, 'theme') ?: 'cms') . '::' . $item->type,
            'cms::invalid'
        ] : 'cms::invalid';
    }
}
