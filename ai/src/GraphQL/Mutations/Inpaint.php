<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Prisma\Files\Image;


final class Inpaint
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        $image = $this->upload( $args['file'], Image::class );
        $mask = $this->upload( $args['mask'], Image::class, 'mask' );

        return $this->ai( fn() => Ai::inpaint( $image, $mask, $args['prompt'] ) );
    }
}
