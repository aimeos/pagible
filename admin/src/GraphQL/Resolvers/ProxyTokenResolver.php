<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\ProxyToken;
use Illuminate\Foundation\Auth\User;


final class ProxyTokenResolver
{
    public function __construct( private ProxyToken $token )
    {
    }


    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     */
    public function __invoke( User $user, array $args, mixed $context ) : string
    {
        return $this->token->make( $user );
    }
}
