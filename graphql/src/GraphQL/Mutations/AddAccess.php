<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Access;


final class AddAccess
{
    /**
     * @param null $rootValue
     * @param array{value: string} $args
     * @return array<int, string>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return app( Access::class )->add( $args['value'] );
    }
}
