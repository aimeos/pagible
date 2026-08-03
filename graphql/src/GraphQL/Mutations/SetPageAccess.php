<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\PageAccess;
use Illuminate\Support\Facades\Auth;


final class SetPageAccess
{
    /**
     * @param null $rootValue
     * @param array{id: list<string>, access?: list<string>|null, descendants?: bool} $args
     */
    public function __invoke( $rootValue, array $args ) : int
    {
        if( !array_key_exists( 'access', $args ) ) {
            throw new Exception( 'The access value must be provided explicitly.' );
        }

        return PageAccess::set(
            $args['id'],
            $args['access'],
            Auth::user(),
            $args['descendants'] ?? false,
        );
    }
}
