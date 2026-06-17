<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class SavePages
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Resource::savePages(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['descendants'] ?? false,
        )->all();
    }
}
