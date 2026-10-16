<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Elements;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


/**
 * Soft-deletes elements.
 */
final class DropElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        $fields = array_keys( $info?->getFieldSelection( 1 ) ?? [] );

        return Elements::dropMany( $args['id'], Auth::user(), $fields )->all();
    }
}
