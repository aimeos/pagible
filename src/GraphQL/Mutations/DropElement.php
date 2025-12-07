<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class DropElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'element:drop', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $items = Element::withTrashed()->whereIn( 'id', $args['id'] )->get();
        $editor = Auth::user()?->name ?? request()->ip();

        foreach( $items as $item )
        {
            $item->editor = $editor;
            $item->delete();
        }

        return $items->all();
    }
}
