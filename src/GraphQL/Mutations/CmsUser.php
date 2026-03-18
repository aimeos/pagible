<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\Authenticatable;
use GraphQL\Error\Error;


final class CmsUser
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): Authenticatable
    {
        /** @var \Illuminate\Foundation\Auth\User $user */
        $user = Auth::guard()->user() ?? throw new Error( 'Not authenticated' );

        $cmsdata = json_encode( $args['cmsdata'] );

        if( $cmsdata && strlen( (string) $cmsdata ) > 65535 ) {
            $msg = 'User data too large (%s KB), maximum is 64 KB';
            throw new Error( sprintf( $msg, round( strlen( (string) $cmsdata ) / 1024 ) ) );
        }

        $user->setAttribute( 'cmsdata', $cmsdata );
        $user->save();

        return $user;
    }
}
