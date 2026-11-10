<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Media;


final class Uncrop
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return Ai::uncrop( Media::uploaded( $args['file'] ), $args['top'] ?? 0, $args['right'] ?? 0, $args['bottom'] ?? 0, $args['left'] ?? 0 );
    }
}
