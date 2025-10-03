<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;


final class PurgeElement
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $items = Element::withTrashed()->whereIn( 'id', $args['id'] )->get();
        Element::whereIn( 'id', $items->pluck( 'id' ) )->forceDelete();

        return $items->all();
    }
}
