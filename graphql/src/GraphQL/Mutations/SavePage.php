<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : PageView
    {
        return Resource::savePage(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['latestId'] ?? null,
            $args['lang'] ?? null,
            !empty( $args['restore'] ),
        );
    }
}
