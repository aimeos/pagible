<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Access;


final class DeleteAccess
{
    /**
     * @param null $rootValue
     * @param array{values: array<int, string>} $args
     * @return array<int, string>
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        return app( Access::class )->delete( $args['values'] );
    }
}
