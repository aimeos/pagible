<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Execution\ResolveInfo;


final class PurgePage
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args, mixed $context = null, ?ResolveInfo $info = null ) : array
    {
        if( isset( $args['lang'] ) ) {
            return Resource::variants( 'purge', $args['id'], $args['lang'], Auth::user(), array_keys( $info?->getFieldSelection( 1 ) ?? [] ) )->all();
        }

        return Resource::purge(
            Page::class,
            $args['id'],
            Auth::user(),
            array_keys( $info?->getFieldSelection( 1 ) ?? [] ),
        )->all();
    }
}
