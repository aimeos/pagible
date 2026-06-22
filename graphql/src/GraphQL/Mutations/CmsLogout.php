<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;


final class CmsLogout
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): ?Authenticatable
    {
        $guard = Auth::guard();
        $user = $guard->user();

        try {
            $guard->logout();

            // Invalidate the session and issue a fresh CSRF token to prevent session reuse/fixation
            if( request()->hasSession() ) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        } catch( \Exception $e ) {
            // No error if logout fails
        }

        return $user;
    }
}