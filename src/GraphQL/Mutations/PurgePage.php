<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class PurgePage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'page:purge', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return Cache::lock( 'cms_pages_' . \Aimeos\Cms\Tenancy::value(), 30 )->get( function() use ( $args ) {
            return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

                $items = Page::withTrashed()->whereIn( 'id', $args['id'] )->get();

                foreach( $items as $item )
                {
                    $item->forceDelete();
                    Cache::forget( Page::key( $item ) );
                }

                return $items->all();
            }, 3 );
        } );
    }
}
