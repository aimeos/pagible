<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Image;


final class Upscale
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        $image = $this->upload( $args['file'], Image::class );

        return $this->ai( fn() => Ai::upscale( $image, $args['factor'] ) );
    }
}
