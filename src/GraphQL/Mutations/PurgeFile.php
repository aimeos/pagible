<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use GraphQL\Error\Error;


final class PurgeFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !Permission::can( 'file:purge', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $items = File::withTrashed()->whereIn( 'id', $args['id'] )->get();

        foreach( $items as $item ) {
            $item->purge();
        }

        return $items->all();
    }
}
