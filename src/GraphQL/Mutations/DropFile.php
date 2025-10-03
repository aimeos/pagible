<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\File;


final class DropFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $items = File::withTrashed()->whereIn( 'id', $args['id'] )->get();
        $editor = Auth::user()?->name ?? request()->ip();

        foreach( $items as $item )
        {
            $item->editor = $editor;
            $item->delete();
        }

        return $items->all();
    }
}
