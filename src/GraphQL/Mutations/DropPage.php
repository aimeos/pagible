<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\Page;


final class DropPage
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $items = Page::withTrashed()->whereIn( 'id', $args['id'] )->get();
        $editor = Auth::user()?->name ?? request()->ip();

        foreach( $items as $item )
        {
            $item->editor = $editor;
            $item->delete();

            Cache::forget( Page::key( $item ) );
        }

        return $items->all();
    }
}
