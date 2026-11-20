<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Refiner;


final class Refine
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        $type = $args['type'] ?? 'content';

        return $type === 'content'
            ? Refiner::content( $args['prompt'], (array) $args['content'], $args['pagetype'] ?? null, $args['context'] ?? null, $args['lang'] ?? null )
            : Refiner::data( $args['prompt'], (array) $args['content'], $type, $args['context'] ?? null, $args['lang'] ?? null );
    }
}
