<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Support\Facades\Auth;


/**
 * Publishes pages or only their language variants if a language is passed.
 */
final class PubPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( isset( $args['lang'] ) ) {
            return Variants::publishMany( $args['id'], $args['lang'], Auth::user(), $args['at'] ?? null )->all();
        }

        return Pages::publishMany( $args['id'], Auth::user(), $args['at'] ?? null )->all();
    }
}
