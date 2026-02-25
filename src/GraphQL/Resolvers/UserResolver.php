<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Illuminate\Foundation\Auth\User;


class UserResolver
{
    /**
     * @param array<string, mixed> $args
     * @param mixed $context
     * @return array<string, mixed>
     */
    public function permission( User $user, array $args, mixed $context ): array
    {
        return \Aimeos\Cms\Permission::get( $user );
    }
}
