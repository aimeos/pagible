<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class MovePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : PageView
    {
        return Resource::movePage( $args['id'], $args['ref'] ?? null, $args['parent'] ?? null, Auth::user() );
    }
}
