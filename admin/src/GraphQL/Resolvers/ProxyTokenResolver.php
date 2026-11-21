<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Permission;
use Aimeos\Cms\ProxyToken;
use Illuminate\Foundation\Auth\User;


final class ProxyTokenResolver
{
    public function __construct( private ProxyToken $token )
    {
    }


    /**
     * Returns a proxy token only to CMS editors allowed to view files.
     *
     * @param array<string, mixed> $args
     * @param mixed $context
     */
    public function __invoke( User $user, array $args, mixed $context ) : ?string
    {
        return Permission::can( 'file:view', $user ) ? $this->token->make( $user ) : null;
    }
}
