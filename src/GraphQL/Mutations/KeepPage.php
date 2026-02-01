<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class KeepPage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'page:keep', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $items = Page::withTrashed()->whereIn( 'id', $args['id'] )->get();
            $editor = Auth::user()?->name ?? request()->ip();

            foreach( $items as $item )
            {
                $item->editor = $editor;
                $item->restore();
            }

            return $items->all();
        }, 3 );
    }
}
