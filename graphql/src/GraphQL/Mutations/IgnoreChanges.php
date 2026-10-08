<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class IgnoreChanges
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, Page>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Resource::ignoreVariants( $args['id'], $args['lang'], Auth::user() )->all();
    }
}
