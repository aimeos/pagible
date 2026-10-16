<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Facades\Elements;
use Illuminate\Support\Facades\Auth;


final class SaveElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : Element
    {
        return Elements::save( $args['id'], $args['input'] ?? [], Auth::user(), $args['latestId'] ?? null );
    }
}
