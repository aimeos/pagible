<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;


final class Upscale
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return Ai::upscale( Media::uploaded( $args['file'] ), $args['factor'] );
    }
}
