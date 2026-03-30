<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Auth;


final class KeepElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Resource::restore( Element::class, $args['id'], Utils::editor( Auth::user() ) )->all();
    }
}
