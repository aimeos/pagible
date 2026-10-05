<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;


final class Refine
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        Ai::checkInput( $content = $args['content'] ?: [] );

        return $this->ai( fn() => Ai::refine( $args['prompt'], (array) $content, $args['type'] ?? 'content',
            $args['pagetype'] ?? null, $args['context'] ?? null, $args['lang'] ?? null ) );
    }
}
