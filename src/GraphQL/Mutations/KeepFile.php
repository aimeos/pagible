<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class KeepFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'file:keep', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $items = File::withTrashed()->whereIn( 'id', $args['id'] )->get();
        $editor = Auth::user()?->name ?? request()->ip();

        foreach( $items as $item )
        {
            $item->editor = $editor;
            $item->restore();
        }

        return $items->all();
    }
}
