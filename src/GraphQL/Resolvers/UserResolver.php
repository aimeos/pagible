<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Resolvers;

use Illuminate\Foundation\Auth\User;


class UserResolver
{
    public function permission( User $user, array $args, $context )
    {
        return \Aimeos\Cms\Permission::get( $user );
    }
}
