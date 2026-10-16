<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Support\Facades\Auth;


final class SavePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : PageView
    {
        if( isset( $args['lang'] ) )
        {
            return empty( $args['restore'] )
                ? Variants::save( $args['id'], $args['lang'], $args['input'] ?? [], Auth::user(), $args['latestId'] ?? null )
                : Variants::revert( $args['id'], $args['lang'], $args['input'] ?? [], Auth::user(), $args['latestId'] ?? null );
        }

        return Pages::save( $args['id'], $args['input'] ?? [], Auth::user(), $args['latestId'] ?? null );
    }
}
