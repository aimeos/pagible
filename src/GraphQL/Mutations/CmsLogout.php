<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use App\Models\User;


final class CmsLogout
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): ?User
    {
        $guard = Auth::guard();
        $user = $guard->user();

        try {
            $guard->logout();
        } catch( \Exception $e ) {
            // No error if logout fails
        }

        return $user;
    }
}