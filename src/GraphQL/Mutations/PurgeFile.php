<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Models\File;


final class PurgeFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $items = File::withTrashed()->whereIn( 'id', $args['id'] )->get();

        foreach( $items as $item ) {
            $item->purge();
        }

        return $items->all();
    }
}
