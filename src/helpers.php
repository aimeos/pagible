<?php

/**
 * @license MIT, http://opensource.org/licenses/MIT
 */


if( !function_exists( 'cms' ) )
{
    function cms( ?object $item, ?string $prop, $default = null )
    {
        if( is_null( $item ) || is_null( $prop ) ) {
            return $default;
        }

        if( $item instanceof \Illuminate\Support\Collection ) {
            return $item->get( $prop );
        }

        if( \Aimeos\Cms\Permission::can( 'page:view', auth()->user() ) ) {
            return @$item->latest?->data?->{$prop}
                ?? @$item->latest?->aux?->{$prop}
                ?? @$item->latest?->{$prop}
                ?? @$item->{$prop}
                ?? $default;
        }

        return @$item->{$prop} ?? $default;
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
