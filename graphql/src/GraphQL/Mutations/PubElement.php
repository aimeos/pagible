<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Auth;


final class PubElement
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        Validation::publishAt( $args['at'] ?? null );

        return Resource::publish( Element::class, $args['id'], Utils::editor( Auth::user() ), $args['at'] ?? null )->all();
    }
}
