<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;


final class Write
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return $this->ai( fn() => Ai::write( $args['prompt'], Ai::files( $args['files'] ?? [] ), $args['context'] ?? null ) );
    }
}
