<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Variants;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


final class IgnoreChanges
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, PageView>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        return Variants::ignoreMany( $args['id'], $args['lang'], Auth::user(), array_keys( $info?->getFieldSelection( 1 ) ?? [] ) )->all();
    }
}
