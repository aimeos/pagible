<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\User;
use GraphQL\Error\Error;


final class CmsLogin
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): User
    {
        $guard = Auth::guard();

        if( !$guard->attempt( $args ) ) {
            throw new Error( 'Invalid credentials' );
        }

        return $guard->user();
    }
}