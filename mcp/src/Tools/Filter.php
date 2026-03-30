<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Tools;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class Filter
{
    /**
     * Applies trashed filter to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @param string|null $trashed 'without' (default), 'with', or 'only'
     */
    public static function trashed( Builder $query, ?string $trashed ) : void
    {
        switch( $trashed ) {
            case 'with': $query->withoutGlobalScope( SoftDeletingScope::class ); break;
            case 'only': $query->withoutGlobalScope( SoftDeletingScope::class )->whereNotNull( 'deleted_at' ); break;
        }
    }


    /**
     * Applies publish status filter to a query joined with cms_versions.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @param string|null $publish 'PUBLISHED', 'DRAFT', or 'SCHEDULED'
     */
    public static function publish( Builder $query, ?string $publish ) : void
    {
        switch( $publish ) {
            case 'PUBLISHED': $query->where( 'cms_versions.published', true ); break;
            case 'DRAFT': $query->where( 'cms_versions.published', false ); break;
            case 'SCHEDULED': $query->where( 'cms_versions.publish_at', '!=', null )
                ->where( 'cms_versions.published', false ); break;
        }
    }
}
