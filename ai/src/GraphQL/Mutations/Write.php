<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Ai;
use Illuminate\Support\Facades\Auth;


final class Write
{
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : string
    {
        return $this->ai( fn() => Ai::write( $args['prompt'], Ai::files( $args['files'] ?? [], Auth::user() ), $args['context'] ?? null ) );
    }
}
