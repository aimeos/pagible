<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;
use Illuminate\Support\Facades\Auth;


final class Imagine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return Ai::imagine( $args['prompt'], Media::load( $args['files'] ?? [], Auth::user() ), $args['context'] ?? null );
    }
}
