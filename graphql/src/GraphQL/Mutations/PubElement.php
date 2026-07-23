<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Publication;
use Illuminate\Support\Facades\Auth;


final class PubElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Publication::publish( Element::class, $args['id'], Auth::user(), $args['at'] ?? null )->all();
    }
}
