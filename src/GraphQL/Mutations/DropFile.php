<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class DropFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'file:drop', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $items = File::withTrashed()->whereIn( 'id', $args['id'] )->get();
            $editor = Auth::user()->name ?? request()->ip();

            foreach( $items as $item )
            {
                /** @var File $item */
                $item->editor = $editor;
                $item->delete();
            }

            return $items->all();
        }, 3 );
    }
}
