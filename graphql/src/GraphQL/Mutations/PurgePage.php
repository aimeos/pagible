<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;


final class PurgePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
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
