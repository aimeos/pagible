<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Illuminate\Support\Facades\Auth;
use Aimeos\Prisma\Files\Image;


final class Imagine
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return $this->ai( fn() => Ai::imagine( $args['prompt'], Ai::files( $args['files'] ?? [], Auth::user(), Image::class ), $args['context'] ?? null ) );
    }
}
