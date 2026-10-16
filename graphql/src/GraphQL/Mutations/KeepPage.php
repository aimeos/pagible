<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


/**
 * Restores pages or only their language variants if a language is passed.
 */
final class KeepPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        $fields = array_keys( $info?->getFieldSelection( 1 ) ?? [] );

        if( isset( $args['lang'] ) ) {
            return Variants::restoreMany( $args['id'], $args['lang'], Auth::user(), $fields )->all();
        }

        return Pages::restoreMany( $args['id'], Auth::user(), $fields )->all();
    }
}
