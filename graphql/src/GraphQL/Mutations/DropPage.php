<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Pages;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


/**
 * Soft-deletes pages.
 */
final class DropPage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        $fields = array_keys( $info?->getFieldSelection( 1 ) ?? [] );

        return Pages::dropMany( $args['id'], Auth::user(), $fields )->all();
    }
}
