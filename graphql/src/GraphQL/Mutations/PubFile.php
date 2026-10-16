<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Facades\Files;
use Illuminate\Support\Facades\Auth;


/**
 * Publishes files.
 */
final class PubFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return Files::publishMany( $args['id'], Auth::user(), $args['at'] ?? null )->all();
    }
}
