<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Queries;

use Aimeos\Cms\Ai;
use Aimeos\Cms\GraphQL\Mutations\ValidatesInputs;
use Aimeos\Cms\Resource;
use Illuminate\Support\Facades\Auth;


final class Translation
{
    use ValidatesInputs;


    /**
     * Returns the proposed translation of the page into the language without saving it.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return $this->ai( fn() => Resource::translation( $args['id'], $args['lang'], Ai::translator( Auth::user() ) ) );
    }
}
