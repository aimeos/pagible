<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\Models\Base;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;


/**
 * Helpers for the item lists returned by the resource methods.
 *
 * @internal
 */
final class Items
{
    /**
     * Returns the single item of a result list.
     *
     * @template T of Base
     * @param class-string<T> $model Model class of the item
     * @param Collection<int, covariant Base> $items Result list
     * @param string $id ID of the requested item
     * @return T Changed item
     * @throws ModelNotFoundException If the item doesn't exist
     */
    public static function one( string $model, Collection $items, string $id ) : Base
    {
        $item = $items->first();

        if( !$item instanceof $model ) {
            throw ( new ModelNotFoundException() )->setModel( $model, [$id] );
        }

        return $item;
    }
}
