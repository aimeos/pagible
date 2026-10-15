<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Pages;
use Illuminate\Support\Facades\Auth;


final class AddPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : PageView
    {
        return Pages::add(
            $args['input'] ?? [],
            Auth::user(),
            $args['ref'] ?? null,
            $args['parent'] ?? null,
        );
    }
}
